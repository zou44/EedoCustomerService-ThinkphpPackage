<?php
/* =============================================================================#
# Description:
#============================================================================= */

use think\Console;

if (class_exists(Console::class)) {
    Console::addDefaultCommands([
        'eedo' => '\\SuperPig\\EedoCustomerService\\command\\Start',
    ]);
}