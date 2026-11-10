<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use TelegramBotEssentials\GatewayCard\Tests\TestCase;
use TelegramBotEssentials\GatewayCard\Tests\WebTestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(WebTestCase::class, RefreshDatabase::class)->in('Web');
