<?php

use CRSCompany\Backster\Classes\BackupManager;

// Route that uses the backup function
// Route::get('backup', function() {
//     $result = BackupManager::createCompleteBackup();
    
//     if ($result['success']) {
//         return response()->json($result);
//     } else {
//         return response()->json($result, 500);
//     }
// });