<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

// init the app
$app = new sly\Assets\App($container);
sly_Core::setCurrentApp($app);
$app->initialize();

// ... and run it
$app->run();
