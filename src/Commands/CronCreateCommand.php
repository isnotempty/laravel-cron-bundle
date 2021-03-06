<?php

namespace Tolacika\CronBundle\Commands;

use Cron\Exception\InvalidPatternException;
use Cron\Validator\CrontabValidator;
use Illuminate\Console\Command;
use Tolacika\CronBundle\CronBundle;
use Tolacika\CronBundle\Models\CronJob;

class CronCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron-bundle:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a cron job';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $job = new CronJob();

        // Asking for job name
        $this->output->writeln('');
        $this->output->writeln('The unique name how the job will be referenced.');
        $job->name = $this->askRecursive("Cron job name", function ($name) {
            if (!$name || strlen($name) == 0) {
                // Invalid if empty
                throw new \InvalidArgumentException('Please set a name.');
            }

            if (CronJob::getJobsByName($name, true)->isNotEmpty()) {
                // Invalid if already in use (Excepts deleted)
                throw new \InvalidArgumentException('Name already in use.');
            }

            return $name;
        });

        // Asking for job command
        $this->output->writeln('');
        $this->output->writeln('<info>The command to execute. You may add extra arguments.</info>');
        $job->command = $this->askRecursive("Command", function ($command) {
            // Invalid if can't find through application
            $this->getApplication()->get($command);

            if (!CronBundle::isCommandAllowed($command)) {
                throw new \InvalidArgumentException("Command not allowed");
            }

            return $command;
        });

        // Asking for job schedule
        $this->output->writeln('');
        $this->output->writeln('<info>The schedule in the crontab syntax.</info>');
        $job->schedule = $this->askRecursive("Schedule", function ($schedule) {
            $validator = new CrontabValidator();
            try {
                // Invalid if CrontabValidator can't validate
                $validator->validate($schedule);
            } catch (InvalidPatternException $ex) {
                // Transform InvalidPatternException to InvalidArgumentException
                throw new \InvalidArgumentException($ex->getMessage());
            }

            return $schedule;
        });

        // Asking for job description (optional)
        $this->output->writeln('');
        $this->output->writeln('<info>Some more information about the job.</info>');
        $job->description = $this->askRecursive("Description");

        // Asking for job enabled state
        $this->output->writeln('');
        $this->output->writeln('<info>Should the cron be enabled.</info>');
        $job->enabled = $this->confirm("Enabled", true) ? '1' : '0';

        $job->save();

        $this->output->writeln('');
        $this->output->writeln(sprintf('<info>Cron "%s" was created with #%d id</info>', $job->name, $job->id));

        return 0;
    }

    /**
     * Asking recursively the given question
     * It's catches all InvalidArgumentException to give a retry for the user
     *
     * @param string $question
     * @param \Closure|null $validateClosure
     * @return mixed|string
     */
    private function askRecursive(string $question, ?\Closure $validateClosure = null)
    {
        while (true) {
            try {
                $answer = $this->ask($question);
                if ($validateClosure !== null) {
                    return $validateClosure($answer);
                }

                return $answer;
            } catch (\InvalidArgumentException $exception) {
                $this->output->writeln("<error>" . $exception->getMessage() . "</error>");
            }
        }

        return "";
    }
}
