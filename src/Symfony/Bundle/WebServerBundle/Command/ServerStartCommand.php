<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs a local web server in a background process.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class ServerStartCommand extends ServerCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('address', InputArgument::OPTIONAL, 'Address:port', '127.0.0.1'),
                new InputOption('port', 'p', InputOption::VALUE_REQUIRED, 'Address port number', '8000'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root', null),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force web server startup'),
            ))
            ->setName('server:start')
            ->setDescription('Starts a local web server in the background')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> runs a local web server:

  <info>php %command.full_name%</info>

To change the default bind address and the default port use the <info>address</info> argument:

  <info>php %command.full_name% 127.0.0.1:8080</info>

To change the default document root directory use the <info>--docroot</info> option:

  <info>php %command.full_name% --docroot=htdocs/</info>

If you have a custom document root directory layout, you can specify your own
router script using the <info>--router</info> option:

  <info>php %command.full_name% --router=app/config/router.php</info>

Specifying a router script is required when the used environment is not <comment>"dev"</comment> or
<comment>"prod"</comment>.

See also: http://www.php.net/manual/en/features.commandline.webserver.php

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $cliOutput = $output);

        if (!extension_loaded('pcntl')) {
            $io->error(array(
                'This command needs the pcntl extension to run.',
                'You can either install it or use the "server:run" command instead.',
            ));

            if ($io->ask('Do you want to execute <info>server:run</info> immediately? [Yn] ', true)) {
                $command = $this->getApplication()->find('server:run');

                return $command->run($input, $cliOutput);
            }

            return 1;
        }

        if (null === $documentRoot = $input->getOption('docroot')) {
            $documentRoot = $this->getContainer()->getParameter('kernel.root_dir').'/../web';
        }

        if (!is_dir($documentRoot)) {
            $io->error(sprintf('The document root directory "%s" does not exist.', $documentRoot));

            return 1;
        }

        $env = $this->getContainer()->getParameter('kernel.environment');
        $address = $input->getArgument('address');

        if (false === strpos($address, ':')) {
            $address = $address.':'.$input->getOption('port');
        }

        if (!$input->getOption('force') && $this->isOtherServerProcessRunning($address)) {
            $io->error(array(
                sprintf('A process is already listening on http://%s.', $address),
                'Use the --force option if the server process terminated unexpectedly to start a new web server process.',
            ));

            return 1;
        }

        if (false === $router = $this->determineRouterScript($documentRoot, $input->getOption('router'), $env)) {
            $io->error('Unable to guess the front controller file.');

            return 1;
        }

        if ('prod' === $env) {
            $io->error('Running this server in production environment is NOT recommended!');
        }

        $pid = pcntl_fork();

        if ($pid < 0) {
            $io->error('Unable to start the server process.');

            return 1;
        }

        if ($pid > 0) {
            $io->success(sprintf('Server listening on http://%s', $address));

            return;
        }

        if (posix_setsid() < 0) {
            $io->error('Unable to set the child process as session leader');

            return 1;
        }

        if (null === $process = $this->createServerProcess($io, $address, $documentRoot, $router)) {
            return 1;
        }

        $process->disableOutput();
        $process->start();
        $lockFile = $this->getLockFile($address);
        file_put_contents($lockFile, $documentRoot);

        if (!$process->isRunning()) {
            $io->error('Unable to start the server process');
            unlink($lockFile);

            return 1;
        }

        // stop the web server when the lock file is removed
        while ($process->isRunning()) {
            if (!file_exists($lockFile)) {
                $process->stop();
            }

            sleep(1);
        }
    }

    /**
     * Creates a process to start a local web server.
     *
     * @param SymfonyStyle $io           A SymfonyStyle instance
     * @param string       $address      IP address and port to listen to
     * @param string       $documentRoot The application's document root
     * @param string       $router       The router filename
     *
     * @return Process The process
     */
    private function createServerProcess(SymfonyStyle $io, $address, $documentRoot, $router)
    {
        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find()) {
            $io->error('Unable to find PHP binary to start server.');

            return;
        }

        $script = implode(' ', array_map(array('Symfony\Component\Process\ProcessUtils', 'escapeArgument'), array(
            $binary,
            '-S',
            $address,
            $router,
        )));

        return new Process('exec '.$script, $documentRoot, null, null, null);
    }
}
