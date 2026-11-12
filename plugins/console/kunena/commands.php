<?php

/**
 * Kunena Console Plugin - commands
 *
 * @package       Kunena.Plugins
 * @subpackage    Console
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Console\AbstractCommand;
use Joomla\CMS\Console\CommandIO;

/**
 * kunena:trash:purge - empties Kunena's trash (same as Kunena > Tools > Empty Trash).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaTrashPurgeCommand extends AbstractCommand
{
    protected $name = 'kunena:trash:purge';

    protected $description = 'Empty Kunena\'s trash: permanently delete every trashed thread and post';

    protected $help = 'Permanently deletes every thread and post in Kunena\'s trash, with their attachments (files included), '
        . 'polls, thank-yous and ratings, then updates the forum statistics, like Kunena > Tools > Empty Trash. '
        . 'This cannot be undone. Asks for a confirmation when the command is interactive; --dry-run only counts what '
        . 'would be deleted. Suitable for cron jobs (with --no-interaction).';

    protected $dryRun = true;

    protected $superUser = true;

    /**
     * @param   CommandIO  $io  Input/output
     *
     * @return  integer  Exit code
     *
     * @since 5.3.0
     */
    protected function doExecute(CommandIO $io)
    {
        $io->title('Kunena: Empty Trash');

        if (!kunenaConsoleLoad() || !class_exists('KunenaForumTrash')) {
            $io->error('Kunena is not installed, or its installation has not been completed.');

            return self::FAILURE;
        }

        $counts = KunenaForumTrash::count();
        $io->definitionList(
            array(
                'Threads in the trash' => $counts['topics'],
                'Posts in the trash'   => $counts['messages'],
            ),
            'trash'
        );

        if (!$counts['topics'] && !$counts['messages']) {
            $io->setData('deleted', array('threads' => 0, 'posts' => 0));
            $io->success('The trash is empty.');

            return self::SUCCESS;
        }

        if ($io->isDryRun()) {
            $io->plan(
                sprintf('Permanently delete %d trashed threads (with all their posts)', $counts['topics']),
                array('action' => 'delete', 'type' => 'threads', 'count' => $counts['topics'])
            );
            $io->plan(
                sprintf('Permanently delete %d trashed posts of live threads', $counts['messages']),
                array('action' => 'delete', 'type' => 'posts', 'count' => $counts['messages'])
            );

            return self::SUCCESS;
        }

        if ($io->isInteractive() && !$io->confirm(sprintf('Permanently delete %d threads and %d posts? This cannot be undone.', $counts['topics'], $counts['messages']), false)) {
            $io->warning('Cancelled.');

            return self::REFUSED;
        }

        $skip   = array();
        $result = KunenaForumTrash::purge(0, $skip);
        KunenaForumTrash::finish($result['affected']);

        foreach ($result['errors'] as $error) {
            $io->warning($error);
        }

        $io->setData('deleted', array('threads' => (int) $result['topics'], 'posts' => (int) $result['messages']));

        if ($result['errors']) {
            $io->error(sprintf('Deleted %d threads and %d posts; %d items could not be deleted.', $result['topics'], $result['messages'], count($result['errors'])));

            return self::FAILURE;
        }

        $io->success(sprintf('The trash is empty: %d threads and %d posts were deleted permanently.', $result['topics'], $result['messages']));

        return self::SUCCESS;
    }
}

/**
 * Loads Kunena for a command.
 *
 * @return  boolean  True when Kunena is installed and ready.
 *
 * @since 5.3.0
 */
function kunenaConsoleLoad()
{
    $api = JPATH_ADMINISTRATOR . '/components/com_kunena/api.php';

    if (is_file($api)) {
        require_once $api;
    }

    return class_exists('KunenaForum') && KunenaForum::installed() && class_exists('KunenaSampledata');
}

/**
 * kunena:sampledata:install - installs the sample data (same as Kunena > Tools > Sample Data).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaSampledataInstallCommand extends AbstractCommand
{
    protected $name = 'kunena:sampledata:install';

    protected $description = 'Install Kunena\'s sample data (categories, users, threads and posts to style and test the forum with)';

    protected $help = 'Installs a complete fictional forum: sections and categories, sample users (unusable for signing in) with generated avatars, '
        . 'moderators, an announcement, and about 60 threads with 400 posts (polls, BBCode, image attachments, thank-yous, '
        . 'sticky, locked, unapproved and trashed posts), like Kunena > Tools > Sample Data. Refused when the sample data '
        . 'is already installed. --dry-run shows what would be created. For development and test sites.';

    protected $dryRun = true;

    protected $superUser = true;

    /**
     * @param   CommandIO  $io  Input/output
     *
     * @return  integer  Exit code
     *
     * @since 5.3.0
     */
    protected function doExecute(CommandIO $io)
    {
        $io->title('Kunena: Install Sample Data');

        if (!kunenaConsoleLoad()) {
            $io->error('Kunena is not installed, or its installation has not been completed.');

            return self::FAILURE;
        }

        if (KunenaSampledata::isInstalled()) {
            $io->error('The sample data is already installed. Remove it first (kunena:sampledata:remove).');

            return self::REFUSED;
        }

        $plan = KunenaSampledata::getPlan();

        if ($io->isDryRun()) {
            $io->plan(
                sprintf('Create %d categories, %d users, %d threads and %d posts', $plan['categories'], $plan['users'], $plan['topics'], $plan['posts']),
                array('action' => 'install') + $plan
            );

            return self::SUCCESS;
        }

        $state  = array();
        $result = KunenaSampledata::install(0, $state);
        $io->setData('installed', $plan);
        $io->success($result['message'] . sprintf(' %d categories, %d users, %d threads, %d posts.', $plan['categories'], $plan['users'], $plan['topics'], $plan['posts']));

        return self::SUCCESS;
    }
}

/**
 * kunena:sampledata:remove - removes the sample data (same as Kunena > Tools > Sample Data).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaSampledataRemoveCommand extends AbstractCommand
{
    protected $name = 'kunena:sampledata:remove';

    protected $description = 'Remove Kunena\'s sample data';

    protected $help = 'Deletes exactly what kunena:sampledata:install (or Kunena > Tools > Sample Data) created: sample categories, '
        . 'users, threads, posts and attachments. Posts which other users added to sample threads are kept with their thread, '
        . 'and so are sample categories which contain other threads. Asks for a confirmation when interactive; --dry-run '
        . 'shows what would be removed.';

    protected $dryRun = true;

    protected $superUser = true;

    /**
     * @param   CommandIO  $io  Input/output
     *
     * @return  integer  Exit code
     *
     * @since 5.3.0
     */
    protected function doExecute(CommandIO $io)
    {
        $io->title('Kunena: Remove Sample Data');

        if (!kunenaConsoleLoad()) {
            $io->error('Kunena is not installed, or its installation has not been completed.');

            return self::FAILURE;
        }

        $counts = KunenaSampledata::getCounts();

        if (!array_sum($counts)) {
            $io->success('No sample data is installed.');

            return self::SUCCESS;
        }

        if ($io->isDryRun()) {
            $io->plan(
                sprintf('Remove %d sample categories, %d users and %d threads', $counts['category'], $counts['user'], $counts['topic']),
                array('action' => 'remove') + $counts
            );

            return self::SUCCESS;
        }

        if ($io->isInteractive() && !$io->confirm('Remove the sample data? This cannot be undone.', false)) {
            $io->warning('Cancelled.');

            return self::REFUSED;
        }

        $state  = array();
        $result = KunenaSampledata::remove(0, $state);

        foreach ($result['kept'] as $note) {
            $io->warning($note);
        }

        $io->setData('removed', $counts);
        $io->setData('kept', $result['kept']);
        $io->success($result['message']);

        return self::SUCCESS;
    }
}

/**
 * kunena:baldrick:process - processes Baldrick's queue (for sites which run it from cron).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaBaldrickProcessCommand extends AbstractCommand
{
    protected $name = 'kunena:baldrick:process';

    protected $description = 'Process the threads waiting for Baldrick, the forum\'s AI first responder';

    protected $help = 'Processes the threads queued for Baldrick (Kunena - Baldrick AI plugin): triage, related threads, answer, '
        . 'replies, spam actions and notifications, like the automatic processing after a page is sent. Run it from cron '
        . '(e.g. every minute, with --no-interaction) when the plugin\'s processing is set to "Command line only". '
        . '--dry-run lists the threads due without calling any AI provider. --unlock takes over threads left "being processed" '
        . 'by a run which stopped without finishing (a PHP error, or the server\'s time or memory limit), so that their '
        . 'errors show in the terminal.';

    protected $dryRun = true;

    protected $superUser = true;

    /**
     * @return  void
     *
     * @since 5.3.0
     */
    protected function configure()
    {
        $this->addOption('limit', null, self::OPTION_REQUIRED, 'Threads to process at most', '10');
        $this->addOption('unlock', null, self::OPTION_NONE, 'Take over threads still marked "being processed" by a run which stopped');
    }

    /**
     * @param   CommandIO  $io  Input/output
     *
     * @return  integer  Exit code
     *
     * @since 5.3.0
     */
    protected function doExecute(CommandIO $io)
    {
        $io->title('Kunena: Baldrick AI');

        if (!kunenaConsoleLoad() || !class_exists('KunenaBaldrick') || !KunenaBaldrick::hasTables()) {
            $io->error('Kunena is not installed, or its installation has not been completed.');

            return self::FAILURE;
        }

        if (!KunenaBaldrick::isEnabled()) {
            $io->warning('The plugin "Kunena - Baldrick AI" is disabled: nothing to do.');

            return self::SUCCESS;
        }

        if (!KunenaBaldrick::isConfigured()) {
            $io->warning('Baldrick has no API key or models yet: nothing to do.');

            return self::SUCCESS;
        }

        $busy  = KunenaBaldrickQueue::countStatus('processing');
        $limit = max(1, (int) $io->getOption('limit'));

        if ($busy && $io->getOption('unlock') && !$io->isDryRun()) {
            KunenaBaldrickQueue::unlock();
        }

        $due = KunenaBaldrickQueue::countDue();

        $io->definitionList(array('Threads due' => $due, 'Being processed' => $busy), 'queue');

        if ($busy && !$io->getOption('unlock')) {
            $io->text(sprintf(
                '%d thread(s) are being processed. A run which stopped without finishing keeps a thread for 10 minutes; '
                . '--unlock takes it over now (only when no other run is working on it).',
                $busy
            ));
        }

        if ($io->isDryRun()) {
            $io->plan(
                sprintf('Process %d of the %d threads due', min($due, $limit), $due),
                array('action' => 'process', 'due' => $due, 'limit' => $limit)
            );

            return self::SUCCESS;
        }

        if (!$due) {
            $io->setData('processed', array());
            $io->success('No thread is waiting.');

            return self::SUCCESS;
        }

        KunenaForum::setup();

        $results = KunenaBaldrickEngine::run($limit, 3600);
        $rows    = array();
        $failed  = 0;

        foreach ($results as $id => $result) {
            $rows[]  = array('job' => (int) $id, 'result' => $result);
            $failed += strpos($result, 'failed') === 0 ? 1 : 0;
        }

        $io->table(array('job' => 'Job', 'result' => 'Result'), $rows, 'processed');

        if ($failed) {
            $io->error(sprintf('Processed %d threads, %d failed.', count($results), $failed));

            return self::FAILURE;
        }

        $io->success(sprintf('Processed %d threads.', count($results)));

        return self::SUCCESS;
    }
}

/**
 * kunena:baldrick:index - creates the full-text index Baldrick uses to find related threads (Kunena > Baldrick AI has the
 * same button). For large forums, where building it from the browser could hit time limits.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaBaldrickIndexCommand extends AbstractCommand
{
    protected $name = 'kunena:baldrick:index';

    protected $description = 'Create the full-text index Baldrick AI uses to find related forum threads';

    protected $help = 'Creates the full-text index of the forum posts (MySQL/MariaDB FULLTEXT, PostgreSQL GIN), so that Baldrick '
        . 'searches every post quickly instead of reading the latest posts (the plain search). Takes a few seconds per '
        . '100,000 posts; on MySQL/MariaDB new posts wait until it is done. Not available on SQLite. --dry-run shows the '
        . 'current state only.';

    protected $dryRun = true;

    protected $superUser = true;

    /**
     * @param   CommandIO  $io  Input/output
     *
     * @return  integer  Exit code
     *
     * @since 5.3.0
     */
    protected function doExecute(CommandIO $io)
    {
        $io->title('Kunena: Baldrick AI search index');

        if (!kunenaConsoleLoad() || !class_exists('KunenaBaldrickRetriever')) {
            $io->error('Kunena is not installed, or its installation has not been completed.');

            return self::FAILURE;
        }

        $status = KunenaBaldrickRetriever::indexStatus();
        $io->definitionList(array('Search index' => $status), 'index');

        if ($status === 'unsupported') {
            $io->warning('This database has no full-text index (SQLite): Baldrick uses the plain search.');

            return self::SUCCESS;
        }

        if ($status === 'present') {
            $io->success('The search index exists.');

            return self::SUCCESS;
        }

        if ($io->isDryRun()) {
            $io->plan('Create the full-text index of the forum posts', array('action' => 'create', 'type' => 'index'));

            return self::SUCCESS;
        }

        $start = microtime(true);

        try {
            KunenaBaldrickRetriever::createIndex();
        } catch (Exception $e) {
            $io->error('The index could not be created: ' . $e->getMessage());

            return self::FAILURE;
        }

        $io->setData('index', array('created' => true, 'seconds' => round(microtime(true) - $start, 1)));
        $io->success(sprintf('The search index was created in %.1f seconds.', microtime(true) - $start));

        return self::SUCCESS;
    }
}

/**
 * kunena:baldrick:check - tells whether Baldrick would answer a post, and which rule stops it; --queue queues the thread
 * when the rules allow it (Baldrick only decides when a post becomes visible, so a post stopped by a rule which no longer
 * applies, e.g. a poster's rights fixed later, is never reconsidered on its own).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaBaldrickCheckCommand extends AbstractCommand
{
    protected $name = 'kunena:baldrick:check';

    protected $description = 'Tell whether Baldrick AI would answer a forum post, and why not';

    protected $help = 'Applies Baldrick\'s rules (plugin, category, staff, inactivity, length, reply limit...) to a post as it '
        . 'is now and tells which rule stops it. Baldrick decides only when a post becomes visible: with --queue, a post '
        . 'which passes the rules now is queued for the next processing run.';

    protected $dryRun = true;

    protected $superUser = true;

    /**
     * @return  void
     *
     * @since 5.3.0
     */
    protected function configure()
    {
        $this->addArgument('post', self::ARGUMENT_REQUIRED, 'Post id (the number after #msg in the post\'s link)');
        $this->addOption('queue', null, self::OPTION_NONE, 'Queue the thread when the post passes the rules');
    }

    /**
     * Without --queue the command only reads.
     *
     * @param   array|null  $options    Options of a call
     * @param   array|null  $arguments  Arguments of a call
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public function isReadOnly(?array $options = null, ?array $arguments = null)
    {
        return $options !== null && empty($options['queue']);
    }

    /**
     * @return  boolean
     *
     * @since 5.3.0
     */
    public function hasReadOnlyRuns()
    {
        return true;
    }

    /**
     * @param   CommandIO  $io  Input/output
     *
     * @return  integer  Exit code
     *
     * @since 5.3.0
     */
    protected function doExecute(CommandIO $io)
    {
        $io->title('Kunena: Baldrick AI check');

        if (!kunenaConsoleLoad() || !class_exists('KunenaBaldrick') || !KunenaBaldrick::hasTables()) {
            $io->error('Kunena is not installed, or its installation has not been completed.');

            return self::FAILURE;
        }

        KunenaForum::setup();

        $id      = (int) $io->getArgument('post');
        $message = $id > 0 ? KunenaForumMessageHelper::get($id) : null;

        if (!$message || !$message->exists()) {
            $io->error(sprintf('There is no post #%d.', $id));

            return self::FAILURE;
        }

        $why    = '';
        $reason = KunenaBaldrick::getTriggerReason($message, $why);
        $user   = KunenaUserHelper::get((int) $message->userid);

        $io->definitionList(
            array(
                'Post'   => '#' . (int) $message->id . ' in thread #' . (int) $message->thread . ': ' . $message->subject,
                'Author' => $message->userid ? $user->getName() . ' (#' . (int) $message->userid . ')' : 'guest',
                'Staff'  => KunenaBaldrick::isStaff((int) $message->userid, $message->getCategory()) ? 'yes' : 'no',
                'Answer' => $reason ? 'yes (' . $reason . ')' : 'no',
                'Reason' => $reason ? '' : $why,
            ),
            'check'
        );

        if (!$reason) {
            $io->warning('Baldrick would not answer this post: ' . $why);

            return self::SUCCESS;
        }

        if (!$io->getOption('queue')) {
            $io->success('Baldrick would answer this post. Use --queue to queue its thread.');

            return self::SUCCESS;
        }

        if ($io->isDryRun()) {
            $io->plan(sprintf('Queue thread #%d (%s)', (int) $message->thread, $reason), array('action' => 'queue', 'thread' => (int) $message->thread, 'reason' => $reason));

            return self::SUCCESS;
        }

        if (!KunenaBaldrickQueue::add((int) $message->thread, (int) $message->id, $reason)) {
            $io->warning('The thread is already waiting in the queue (a job not started yet now answers from this post, if it is newer).');

            return self::SUCCESS;
        }

        $io->setData('queued', array('thread' => (int) $message->thread, 'post' => (int) $message->id, 'reason' => $reason));
        $io->success(sprintf('Thread #%d is queued; run kunena:baldrick:process (or wait for the automatic processing).', (int) $message->thread));

        return self::SUCCESS;
    }
}
