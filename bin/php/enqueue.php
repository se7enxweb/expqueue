#!/usr/bin/env php
<?php
/**
 * ./console ext:expqueue:enqueue — Queue a job: enqueue <type> --payload='{...}' (test types expqueue.noop, expqueue.sleep, expqueue.fail)
 * The code is in classes/expqueuecommand.php; run with --help for the options.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 * @description Queue a job: enqueue <type> --payload='{...}' (test types expqueue.noop, expqueue.sleep, expqueue.fail)
 */

$root = dirname( __DIR__, 4 );
chdir( $root );
require_once $root . '/autoload.php';
expQueueCommand::main( 'enqueue' );
