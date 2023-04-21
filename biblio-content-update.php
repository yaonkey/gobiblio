<?php
$script_path = './dle-biblio-addbooks.php'; //! путь до скрипта с включенным режимом работы `only_update`

try {
    $upd_book = json_decode(file_get_contents("php://input"));
} catch (Exception $e) {
    die("Error with biblio book updating");
}
if (!empty($upd_book)) {
    require_once($script_path);
    if ($upd_book->state == 'update') {
        UpdateBook($upd_book);
    } else if ($upd_book->state == 'new') {
        AddBook($upd_book);
    } else if ($upd_book->state == 'delete') {
        DeleteBook($upd_book);
    }
}
