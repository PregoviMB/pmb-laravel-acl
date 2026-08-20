<?php

use Pmb\Acl\Tests\TestCase;

// Configure Pest to use the TestCase class for all tests in the Feature and Unit directories
uses(TestCase::class)->in('Feature', 'Unit');