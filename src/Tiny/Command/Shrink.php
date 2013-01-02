<?php
namespace Tiny\Command;

use Guzzle\Common\Exception\ExceptionCollection;
use Guzzle\Plugin\CurlAuth\CurlAuthPlugin;
use Symfony\Component\Console\Command\Command as SymfoCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Tiny\Client;
use Tiny\FileIterator;

class Shrink extends SymfoCommand
{
    const EXIT_FAIL = 1;
    const EXIT_SUCCESS = 0;

    protected $shrinkPrefix = 'shrinked.';
    protected $outputDirectory;
    protected $client;
    protected $configurationFilePath;
    
    public function __construct($name, Client $client) 
    {
        $this->client = $client;
        $this->configurationFilePath = __DIR__ . '/../../../config/api.key.conf.yml';
        
        parent::__construct($name);
    }
    
    protected function configure()
    {
        $this
            ->setDescription("Shrink an image using tiny png service")
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Who do you want to shrink?'
            )
            ->addOption(
               'output-dir',
               null,
               InputOption::VALUE_REQUIRED,
               'Where do you want the shrinked images to go ?'
            )->addOption(
               'no-prefix',
               null,
               InputOption::VALUE_NONE,
               'Do not prefix images'
            )->addOption(
               'override',
               null,
               InputOption::VALUE_NONE,
               'Override existing images'
            )->addOption(
               'no-recursive',
               null,
               InputOption::VALUE_NONE,
               'Do not recurse into directories'
            );

        return $this;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->client->addSubscriber(new CurlAuthPlugin($this->getApiKey(), ''));
        } catch (\Exception $e) {
            $command = $this->getApplication()->find('config:edit-key');

            $subInput = new ArrayInput(array('command' => 'config:edit-key'));
                    
            if (self::EXIT_FAIL === $command->run($subInput, $output)) {
                
                return self::EXIT_FAIL;
            }
            
            $this->client->addSubscriber(new CurlAuthPlugin($command->getApiKey(), ''));
        }
        
        if ($input->getOption('no-prefix')) {
            $this->shrinkPrefix = '';
        }
        
        $this->outputDirectory = $input->getOption('output-dir');
        
        $that = $this;

        $shrinkBag = new FileIterator(
            $input->getArgument('file'),
            function ($file) use ($that, $input) {
                if ($that->getShrinkPrefix() === substr($file->getBaseName(), 0, strlen($that->getShrinkPrefix()))) {
                    
                    return false;
                }
                
                if (!$input->getOption('override')) {
                    
                    return !file_exists($that->getOutputImagePathName($file));
                }
                
                return true;
            },
            !$input->getOption('no-recursive')
        );
        
        if (0 === $countImage = count($shrinkBag)) {
            $output->writeln("<comment>No image are eligible for being shrunk</comment>");

            return self::EXIT_SUCCESS;
        }
        
        if ($countImage > 1) {
            $input->setInteractive(false);
        }
        
        try {
            $responses = $this->client->shrink($shrinkBag);
        } catch (ExceptionCollection $e) {
            $output->writeln(
                "<comment>The following exception(s)"
                . " were encountered</comment>"
            );
            
            foreach ($e as $exception) {
                $output->writeln(sprintf("\t - %s", $exception->getMessage()));
            }
        }
        
        if (0 === count($responses)) {
            $output->writeln(
                "<comment>Operation aborted. Reason is "
                . "#No picture(s) could be sent to tinypng service</comment>"
            );

            return self::EXIT_FAIL;
        }
        
        $processed = 0;
        
        foreach ($responses as $filename => $response) {
            $data = $response->json();
            
            if (isset($data['code'])) {
                 $output->writeln(
                     "<error>Tiny PNG could not shrink the current image."
                      . " Reason is #{$data['code']}: #{$data['message']}</error>"
                 );

                 continue;
            }
             
            try {
                $toShrink = $shrinkBag->findFileByName($filename);
            
                if (!$toShrink instanceof \SplFileInfo) {
                   throw new \Exception();
                }
                
                $imageFileInfo = new \SplFileInfo($this->getOutputImagePathName($toShrink));

                try {
                    $image = $imageFileInfo->openFile('w');
                } catch (\RuntimeException $e) {
                    throw new \Exception(sprintf(
                        'Could Not open the file. Reason is #%s',
                        $e->getMessage()
                    ), $e->getCode(), $e);
                }
                
                $shrinkedImageResponse = $this->client
                    ->get($data['output']['url'])
                    ->send();

                $shrinkedImageResponse->getBody()->seek(0);

                $image->fwrite($shrinkedImageResponse->getBody());
                $image->rewind();
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    "<comment>The image %s could not be rapatriated"
                    . " on the local machine</comment>",
                    $filename
                ));

                $output->writeln(sprintf(
                    "However image is still available at this url"
                    . "<bg=yellow;options=bold>'%s'</bg=yellow;options=bold>",
                    $data['output']['url']
                ));

                continue;
            }

            $output->writeln(sprintf(
                "Image has been successfully shrinked and saved here"
                . " <fg=green>'%s'</fg=green>, compression rate is "
                . "<fg=black;bg=cyan>%s</fg=black;bg=cyan>",
                $image->getPathname(),
                (string) $data['output']['ratio']
            ));
            
            $processed++;
        }
        
        if (0 !== $processed) {
            
            return self::EXIT_SUCCESS;
        }
        
        return self::EXIT_FAIL;
    }
    
    public function getShrinkPrefix()
    {
        return $this->shrinkPrefix;
    }
    
    public function getOutputImagePathName(\SplFileInfo $file, $basename = null)
    {
        return sprintf(
            '%s/%s%s',
            $this->outputDirectory ?: $file->getPathInfo()->getRealPath(),
            $this->shrinkPrefix,
            $basename ?: $file->getBasename()
        );
    }
    
    public function getConfigurationFilePath()
    {
        return $this->configurationFilePath;
    }
    
    public function setConfigurationFilePath($filePath)
    {
        $this->configurationFilePath = $filePath;
        
        return $this;
    }
    
    private function getApiKey()
    {
        if (!file_exists($this->configurationFilePath)) {
           throw new \Exception('Missing configuration file'); 
        }
        
        try {
            $configArray = Yaml::parse($this->configurationFilePath);
        } catch (\Exception $e) {
            throw new \Exception(
                'Error while parsing file',
                $e->getCode(),
                $e
            );
        }

        if (!isset($configArray['api_key'])) {
            throw new \Exception('Missing tinypng api key');
        }

        return $configArray['api_key'];
    }
}
