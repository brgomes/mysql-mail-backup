<?php

if (php_sapi_name() != 'cli') {
    die('Run only on cli version');
}

error_reporting(-1);
ini_set('display_errors', 'on');

$config     = require 'config.php';
$host       = $config['db.host'];
$user       = $config['db.username'];
$pass       = $config['db.password'];
$databases  = $config['db.databases'];

if (!is_array($databases)) {
    $databases = [$databases];
}

$year   = date('Y');
$month  = date('m');
$day    = date('d');
$files  = [];
$body   = "Backup made at $year-$month-$day " . date('H:i:s') . '<br /><br />';

sort($databases);

include 'PHPMailer.php';
include 'SMTP.php';

$mail = new PHPMailer(false, $config);

$mail->IsHTML(true);
$mail->AddAddress($config['mail.sendto']);

foreach ($databases as $db) {
	$backupfile = $year . $month . $day . '-' . $db . '.sql';
	$backupzip  = $year . $month . $day . '-' . $db . '.tar.gz';

	system("mysqldump -h $host -u $user -p$pass -B --add-drop-database --skip-lock-tables $db > $backupfile");

    $filesize = filesize($backupfile);

    if ($filesize > 0) {
        system("tar -czvf $backupzip $backupfile");

        $files[] = $backupzip;

        $body .= '<strong>' . $db . '</strong>: ' . fileSizeConvert($filesize) . '<br />';

        $mail->AddAttachment($backupzip);
    }

    unlink($backupfile);
}

$enviado = false;

if (count($files) > 0) {
    $mail->Subject = $config['mail.subject'];
    $mail->Body    = $body;

    $mail->Send();

    foreach ($files as $file) {
        unlink($file);
    }
}


function fileSizeConvert($bytes) {
    $result = null;
    $bytes = floatval($bytes);
    $arBytes = array(
        0 => array(
            'unit' => 'TB',
            'value' => pow(1024, 4)
        ),
        1 => array(
            'unit' => 'GB',
            'value' => pow(1024, 3)
        ),
        2 => array(
            'unit' => 'MB',
            'value' => pow(1024, 2)
        ),
        3 => array(
            'unit' => 'KB',
            'value' => 1024
        ),
        4 => array(
            'unit' => 'B',
            'value' => 1
        ),
    );

    foreach ($arBytes as $arItem) {
        if ($bytes >= $arItem['value']) {
            $result = $bytes / $arItem['value'];
            $result = str_replace('.', ',' , strval(round($result, 2))) . ' ' . $arItem['unit'];
            break;
        }
    }

    return $result;
}
