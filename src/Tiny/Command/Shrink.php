<?php
namespace Tiny\Command;

use Guzzle\Common\Exception\ExceptionCollection;
use Guzzle\Common\Event;
use Guzzle\Plugin\CurlAuth\CurlAuthPlugin;
use Symfony\Component\Console\Command\Command as SymfoCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Tiny\Client;
use Tiny\Command\Code;
use Tiny\FileIterator;

/**
 * This class is the shrink command
 */
class Shrink extends SymfoCommand
{
    protected $shrinkPrefix = 'shrinked.';
    protected $outputDirectory;
    protected $client;
    protected $configurationFilePath;
    
    /**
     * Constructor
     * 
     * @param   string          $name       The command name
     * @param   \Tiny\Client    $client     A Client instance
     */
    public function __construct($name, Client $client) 
    {
        $this->client = $client;
        $this->configurationFilePath = __DIR__ . '/../../../config/api.key.conf.yml';
        
        parent::__construct($name);
    }
    
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setDescription("Shrink an image using tiny png service")
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'What do you want to shrink?'
            )
            ->addOption(
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Where do you want the shrinked images to go ?'
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

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->client->addSubscriber(new CurlAuthPlugin($this->getApiKey(), ''));
        } catch (\Exception $e) {
            $command = $this->getApplication()->find('config:edit-key');

            $subInput = new ArrayInput(array('command' => 'config:edit-key'));
              
            $subInput->setInteractive($input->isInteractive());
            
            if (Code::EXIT_FAILURE === $command->run($subInput, $output)) {
                
                return Code::EXIT_FAILURE;
            }
            
            $this->client->addSubscriber(new CurlAuthPlugin($command->getApiKey(), ''));
        }
        
        $this->client->getEventDispatcher()->addListener('request.error', function(Event $event) {
            // override guzzle default behavior of throwing exceptions when 4xx & 5xx responses are encountered
            $event->stopPropagation();
        }, -254);
        
        $this->outputDirectory = $input->getOption('output-dir');
        
        $that = $this;

        try {
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
        } catch (\InvalidArgumentException $e) {
            $output->writeln("<error>Invalid input file</error>");

            return Code::EXIT_FAILURE;
        }
        
        if (0 === $countImage = count($shrinkBag)) {
            $output->writeln("<comment>No image are eligible for being shrunk</comment>");

            return Code::EXIT_SUCCESS;
        }
        
        $responses = array();
        
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

            return Code::EXIT_FAILURE;
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
                
                $shrinkedImageResponse = $this->client
                    ->get($data['output']['url'])
                    ->send();

                if (!$shrinkedImageResponse->isSuccessful()) {
                    throw new \Exception();
                }
                
                try {
                    $image = $imageFileInfo->openFile('w');
                } catch (\RuntimeException $e) {
                    throw new \Exception(sprintf(
                        'Could Not open the file. Reason is #%s',
                        $e->getMessage()
                    ), $e->getCode(), $e);
                }
                
                $shrinkedImageResponse->getBody()->seek(0);

                $image->fwrite($shrinkedImageResponse->getBody());
                $image->rewind();
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    "<comment>The image %s could not be rapatriated"
                    . " on the local machine</comment>",
                    $imageFileInfo->getBasename()
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
            
            return Code::EXIT_SUCCESS;
        }
        
        return Code::EXIT_FAILURE;
    }
    
    /**
     * Gets the prefix of the shrinked images
     * 
     * @return  string
     */
    public function getShrinkPrefix()
    {
        return $this->shrinkPrefix;
    }
    
    /**
     * Gets the output pathname of a processed image
     * 
     * @param   \SplFileInfo    $file       A instance of \SplFileInfo
     * @param   string|null     $basename   A custome basename
     * @return  string
     */
    public function getOutputImagePathName(\SplFileInfo $file, $basename = null)
    {
        return sprintf(
            '%s/%s%s',
            $this->outputDirectory ?: $file->getPathInfo()->getRealPath(),
            $this->shrinkPrefix,
            $basename ?: $file->getBasename()
        );
    }
    
    /**
     * Sets the path to the configuration file
     * 
     * @param  string    $filePath  A file path
     * @return Shrink
     */
    public function setConfigurationFilePath($filePath)
    {
        $this->configurationFilePath = $filePath;
        
        return $this;
    }
    
    /**
     * Gets the api key from the configuration file
     * 
     * @return  string
     * @throws  \Exception  In case file does not exists
     * @throws  \Exception  In case file can not be parsed
     * @throws  \Exception  In case api key could not be found
     */
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
