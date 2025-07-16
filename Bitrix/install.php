<?php
require_once './crest.php';

$result = CRest::installApp();
if ($result['rest_only'] === false) { ?>
    <!DOCTYPE html>
    <html lang="ru">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Установка приложения</title>
        <script src="//api.bitrix24.com/api/v1/"></script>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f5f7fa;
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                color: #333;
            }

            .container {
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                padding: 30px;
                width: 400px;
                text-align: center;
            }

            .icon {
                font-size: 48px;
                margin-bottom: 20px;
                color:
                    <?= $result['install'] ? '#2fc06e' : '#ff5752' ?>
                ;
            }

            h1 {
                font-size: 24px;
                margin-bottom: 15px;
                color: #424956;
            }

            p {
                font-size: 16px;
                margin-bottom: 25px;
                line-height: 1.5;
            }

            .btn {
                background-color: #2f81b7;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
                transition: background-color 0.3s;
                font-weight: 600;
            }

            .btn:hover {
                background-color: #236a9a;
            }

            .hidden {
                display: none;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <?php if ($result['install'] == true) { ?>
                <div class="icon">✓</div>
                <h1>Установка завершена</h1>
                <p>Приложение успешно установлено в ваш Битрикс24. Нажмите "Продолжить", чтобы начать работу.</p>
                <button id="continueBtn" class="btn">Продолжить</button>

                <script>
                    BX24.init(function () {
                        document.getElementById('continueBtn').addEventListener('click', function () {
                            BX24.installFinish();
                        });
                    });
                </script>
            <?php } else { ?>
                <div class="icon">✗</div>
                <h1>Ошибка установки</h1>
                <p>При установке приложения произошла ошибка. Пожалуйста, попробуйте еще раз или обратитесь в поддержку.</p>
            <?php } ?>
        </div>
    </body>

    </html>
<?php }

$install_handler = ($_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] === '443' ? 'https' : 'http') . '://'
    . $_SERVER['SERVER_NAME']
    . (in_array($_SERVER['SERVER_PORT'], ['80', '443'], true) ? '' : ':' . $_SERVER['SERVER_PORT'])
    . str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(__DIR__ . '/install_handler.php'));

$uninstall_handler = ($_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] === '443' ? 'https' : 'http') . '://'
    . $_SERVER['SERVER_NAME']
    . (in_array($_SERVER['SERVER_PORT'], ['80', '443'], true) ? '' : ':' . $_SERVER['SERVER_PORT'])
    . str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(__DIR__ . '/uninstall_handler.php'));


CRest::call(
    'crm.deal.userfield.add',
    [
        'fields' => [
            'FIELD_NAME' => 'ID_ORDER_IN_BUS',
            'EDIT_FORM_LABEL' => 'ID заказа в бус',
            'LIST_COLUMN_LABEL' => 'ID заказа в бус',
            'SHOW_IN_LIST' => 'Y',
            'USER_TYPE_ID' => 'string',
            'XML_ID' => 'ID_ORDER_IN_BUS',
            'SETTINGS' => [
                'CAPTION_NO_VALUE' => '',
                'SHOW_NO_VALUE' => 'Y',
            ]
        ]
    ]
);

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