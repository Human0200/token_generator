<?php

require_once './crest.php';
require_once './settings.php';

$result = CRest::installApp();
if ($result['rest_only'] === false) { ?>
	<head>
		<script src="//api.bitrix24.com/api/v1/"></script>
		<?php if ($result['install'] == true) { ?>
			<script>
				BX24.init(function(){
					BX24.installFinish();
				});
			</script>
		<?php }?>
	</head>
	<body>
		<?php if ($result['install'] == true) { ?>
			installation has been finished
		<?php } else { ?>
			installation error
		<?php }?>
	</body>
<?php }

$install_handler = ($_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] === '443' ? 'https' : 'http').'://'
    .$_SERVER['SERVER_NAME']
    .(in_array($_SERVER['SERVER_PORT'], ['80', '443'], true) ? '' : ':'.$_SERVER['SERVER_PORT'])
    .str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(__DIR__.'/../api/handlers/install.php'));

$uninstall_handler = ($_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] === '443' ? 'https' : 'http').'://'
    .$_SERVER['SERVER_NAME']
    .(in_array($_SERVER['SERVER_PORT'], ['80', '443'], true) ? '' : ':'.$_SERVER['SERVER_PORT'])
    .str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(__DIR__.'/../api/handlers/uninstall.php'));

CRest::call(
    'event.bind',
    [
        'event' => 'ONAPPINSTALL',
        'handler' => $install_handler,
    ]
);

CRest::call(
    'event.bind',
    [
        'event' => 'ONAPPUNINSTALL',
        'handler' => $uninstall_handler,
    ]
);

//app.option для приложения
