<?php

use Guzzle\Http\Exception\CurlException;
use Guzzle\Plugin\Mock\MockPlugin;
use Nlegoff\Tiny\Command\Shrink;
use Nlegoff\Tiny\Command\Config;
use Nlegoff\Tiny\Client;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class ShrinkTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecute()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $application->add(new Shrink('client:shrink', $this->getMockedClient($responses)));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image'
        ));

        $testFile = new \SplFileInfo(__DIR__ . '/../../../../resources/image/troll.png');
        $shrinkedTestFile = new \SplFileInfo($command->getOutputImagePathName($testFile));

        $this->assertTrue($shrinkedTestFile->isFile());
        unlink($shrinkedTestFile->getRealPath());

        $this->assertRegExp('/Image has been successfully shrinked/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteOverride()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $application->add(new Shrink('client:shrink', $this->getMockedClient($responses)));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image',
            '--override'  => true
        ));

        $testFile = new \SplFileInfo(__DIR__ . '/../../../../resources/image/troll.png');
        $shrinkedTestFile = new \SplFileInfo($command->getOutputImagePathName($testFile));

        $this->assertTrue($shrinkedTestFile->isFile());
        unlink($shrinkedTestFile->getRealPath());

        $this->assertRegExp('/Image has been successfully shrinked/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteRepatriationFailure()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back.failed')
        );

        $application->add(new Shrink('client:shrink', $this->getMockedClient($responses)));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image'
        ));

        $this->assertRegExp('/could not be rapatriated on the local machine/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteErrorApi()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.failed'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $application->add(new Shrink('client:shrink', $this->getMockedClient($responses)));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image'
        ));

        $this->assertRegExp('/Tiny PNG could not shrink the current image/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteNoEligibleImages()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $application->add(new Shrink('client:shrink', $this->getMockedClient($responses)));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image_already_shrinked'
        ));

        $this->assertRegExp('/No image are eligible for being shrunk/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteCurlException()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $client = $this->getMockedClient($responses);

        $client->getEventDispatcher()->addListener('request.before_send', function() {
            throw new CurlException('Connection timed out');
        });

        $application->add(new Shrink('client:shrink', $client));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image'
        ));

        $this->assertRegExp('/Connection timed out/', $commandTester->getDisplay());
        $this->assertRegExp('/Operation aborted/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteBadConfiguration()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $client = $this->getMockedClient($responses);

        $application->add(new Shrink('client:shrink', $client));
        $application->add(new Config('config:edit-key'));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.bad.key.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image'
        ), array(
            'interactive'   => false
        ));

        $testFile = new \SplFileInfo(__DIR__ . '/../../../../resources/image/troll.png');
        $shrinkedTestFile = new \SplFileInfo($command->getOutputImagePathName($testFile));

        $this->assertTrue($shrinkedTestFile->isFile());

        unlink($shrinkedTestFile->getRealPath());

        $this->assertRegExp('/Your api key has been successfully saved/', $commandTester->getDisplay());
        $this->assertRegExp('/Image has been successfully shrinked/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteConfigurationNotParsable()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $client = $this->getMockedClient($responses);

        $application->add(new Shrink('client:shrink', $client));
        $application->add(new Config('config:edit-key'));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.bad.parsable.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image'
        ), array(
            'interactive'   => false
        ));

        $testFile = new \SplFileInfo(__DIR__ . '/../../../../resources/image/troll.png');
        $shrinkedTestFile = new \SplFileInfo($command->getOutputImagePathName($testFile));

        $this->assertTrue($shrinkedTestFile->isFile());
        unlink($shrinkedTestFile->getRealPath());

        $this->assertRegExp('/Your api key has been successfully saved/', $commandTester->getDisplay());
        $this->assertRegExp('/Image has been successfully shrinked/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::execute
     * @covers Tiny\Command\Shrink::__construct
     * @covers Tiny\Command\Shrink::configure
     * @covers Tiny\Command\Shrink::getApiKey
     */
    public function testExecuteConfigurationNoExistent()
    {
        $application = new Application();

        $responses = array(
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.api.success'),
            MockPlugin::getMockFile(__DIR__ . '/../../../../resources/responses/response.image.back')
        );

        $client = $this->getMockedClient($responses);

        $application->add(new Shrink('client:shrink', $client));
        $application->add(new Config('config:edit-key'));

        $command = $application->find('client:shrink');
        /* @var $command Shrink */
        $command->setConfigurationFilePath(__DIR__ . '/../../../../resources/configurations/api.key.conf.not.exists.yml');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'file'      => __DIR__ . '/../../../../resources/image'
        ), array(
            'interactive'   => false
        ));

        $testFile = new \SplFileInfo(__DIR__ . '/../../../../resources/image/troll.png');
        $shrinkedTestFile = new \SplFileInfo($command->getOutputImagePathName($testFile));

        $this->assertTrue($shrinkedTestFile->isFile());
        unlink($shrinkedTestFile->getRealPath());

        $this->assertRegExp('/Your api key has been successfully saved/', $commandTester->getDisplay());
        $this->assertRegExp('/Image has been successfully shrinked/', $commandTester->getDisplay());
    }

    /**
     * @covers Tiny\Command\Shrink::getOutputImagePathName
     */
    public function testGetOutputImagePathName()
    {
        $file = new \SplFileInfo(__FILE__);
        $command = new Shrink('client:shrink', new Client());

        $this->assertRegexp('/shrinked.titi.png/', $command->getOutputImagePathName($file, 'titi.png'));
    }

    protected function getMockedClient(array $responses)
    {
        $plugin = new MockPlugin($responses, true, true);

        $client = new Client();
        $client->addSubscriber($plugin);

        return $client;
    }
}
