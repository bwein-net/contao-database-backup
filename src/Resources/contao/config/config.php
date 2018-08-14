<?php

// Cron jobs
$GLOBALS['TL_CRON']['daily']['bwein_database_backup_auto'] = ['bwein.database_backup.listener.cronjob', 'onDaily'];

// Hooks
$GLOBALS['TL_HOOKS']['getUserNavigation'][] = ['bwein.database_backup.listener.navigation', 'onGetUserNavigation'];

// Backend Module for Permissions
$GLOBALS['BE_MOD']['system']['database_backup'] = [];
