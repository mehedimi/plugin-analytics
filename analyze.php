#!/usr/bin/env php
<?php

use Symfony\Component\Console\Application;

require './vendor/autoload.php';


$app = new Application('WP Plugin Analytics', 1.0);


$app->add(new PositionCommand());


$app->run();
