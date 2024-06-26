#!/usr/bin/php

<?php
#################################################
# Скрипт добавления книг в базу данных реферала #
# Больше информации по конфигурированию         #
# находится в файле README.md                   #
#################################################

################
# КОНФИГУРАЦИЯ #
# Общее #
$referal_key = ""; # Ключ реферала
$only_update = false; # ВАЖНО! Переключение режимов работы скрипта - только обновление контента (true) или загрузка всего каталога (false)
$check_exist = false; # Проверка наличия книг в действующем каталоге
$create_new_categories = true; # Создание новых категорий, если требуемая отсутствует
$save_images_to_server = false; # Сохранение изображений на сервер (если нет, в бд добавляется ссылка)

# База данных #
$db_driver = "mysql"; # Драйвер базы данных
$db_host = "localhost"; # Хост, на котором находится база данных
$db_port = "3306"; # Порт, на котором находится база данных
$db_user = ""; # Пользователь базы данных
$db_password = ""; # Пароль пользователя базы данных
$db_name = ""; # Наименование базы данных для добавления книг
$db_table = "dle_post"; # Наименование таблицы в базе данных для добавления книг

# Поля базы данных #
$book_id = "book_id"; # Поле идентификатора книги из Biblio
$book_title = "title"; # Поле наименования книги из Biblio
$book_bio = "short_story"; # Поле описания книги из Biblio
$book_cover = "book_cover"; # Поле обложки из Biblio
$book_duration = ""; # Поле длительности книги из Biblio
$book_rating = ""; # Поле рейтинга книги из Biblio
$book_amount = "book_amount"; # Поле цены книги из Biblio
$book_plus18 = "book_plus_18"; # Поле ограничения книги по возрасту из Biblio (18+)
$book_plus16 = "book_plus_16"; # Поле ограничения книги по возрасту из Biblio (16+)
$book_with_music = "book_with_music"; # Поле обозначения книг с музыкой из Biblio
$book_not_finished = ""; # Поле обозначения неоконченности книги из Biblio
$book_author = "book_author"; # Поле автора книги из Biblio
$book_reader = "book_reader"; # Поле читателя книги из Biblio
$book_series = "book_series"; # Поле серии книги из Biblio
$book_genres = "category"; # Поле жанра книги из Biblio
$book_lang = ""; # Поле языка книги из Biblio
$book_publish_date = ""; # Поле даты публикации книги из Biblio
$book_sale_closed = ""; # Поле доступности книги для продажи из Biblio

$meta_translater = "book_translater"; # Поле переводчика книги из Biblio
$meta_copyright = ""; # Поле лицензии книги из Biblio
$meta_publisher = "book_publisher"; # Поле издателя книги из Biblio

# Дополнительные поля для DLE
$dle_author = "autor";
$dle_fields = "xfields";
$dle_comm = "allow_comm";
$dle_main = "allow_main";
$dle_approve = "approve";
$dle_alt = "alt_name";
$dle_category_db = "dle_category";
################################################################################

# Переменные запроса (не изменять)
$current_page = 0; # Текущая страница
$last_page = 0; # Последняя страница
$biblio_api_url = "https://api.bibliovk.ru/api/ref/data/catalog/full?page="; # URL API
$database = new stdClass;

/**
 * Класс книги с информацией о ней
 */
class Book
{
    function __construct(
        $id,
        $title,
        $bio,
        $cover,
        $duration,
        $rating,
        $amount,
        $plus18,
        $plus16,
        $with_music,
        $not_finished,
        $author,
        $reader,
        $series,
        $genres,
        $lang,
        $publish_date,
        $sale_closed
    ) {
        $this->id = $id ?: null;
        $this->title = $title ?: "";
        $this->bio = $bio ?: "";
        $this->cover = $cover ?: "";
        $this->duration = $duration ?: null;
        $this->rating = $rating ?: null;
        $this->amount = $amount ?: null;
        $this->plus18 = $plus18 ?: false;
        $this->plus16 = $plus16 ?: false;
        $this->with_music = $with_music ?: false;
        $this->not_finished = $not_finished ?: false;
        $this->author = $author ?: "";
        $this->reader = $reader ?: "";
        $this->series = $series ?: "";
        $this->genres = $genres ?: "";
        $this->lang = $lang ?: "";
        $this->publish_date = $publish_date ?: "";
        $this->sale_closed = $sale_closed ?: false;
    }
}

/**
 * Класс с метаинформацией о книге
 */
class Meta
{
    function __construct($translater, $copyright, $publisher)
    {
        $this->translater = $translater ?: "";
        $this->copyright = $copyright ?: "";
        $this->publisher = $publisher ?: "";
    }
}

/**
 * Метод получения книг с одного запроса
 */
function GetBooksFromPage()
{
    PrintMsg("Getting books\n");
    global $current_page;
    global $biblio_api_url;
    global $referal_key;

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "Content-Type: application/json\r\n" .
            "X-Biblio-Auth: Bearer $referal_key\r\n"
        ]
    ];
    $ctx = stream_context_create($opts);

    $books = json_decode(file_get_contents($biblio_api_url . $current_page, false, $ctx), true);
    $current_page++;

    return $books;
}

/**
 * Метод для подключения к базе данных
 */
function NewDatabaseConnection()
{
    PrintMsg("Connection to database\n");
    global $db_driver;
    global $db_name;
    global $db_host;
    global $db_user;
    global $db_password;
    global $database;

    try {
        $database = new PDO("$db_driver:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_password);
        PrintMsg("Connection ok\n");
    } catch (PDOException $e) {
        die("PDO Error: " . $e->getMessage());
    }
}

/**
 * Метод для получения категорий из DLE
 */
function GetCategories($categories)
{
    global $database;
    global $dle_category_db;
    global $create_new_categories;

    $result = '';
    $cats = explode(", ", $categories);

    foreach ($cats as $category) {
        $sql = "SELECT id FROM $dle_category_db cat WHERE cat.name = '$category'";
        try {
            $cat = $database->query($sql)->fetch();
            if ($cat) {
                $current_cat = $cat["id"];
                PrintMsg("Категория $category ($current_cat)\n");
            } else if ($create_new_categories) {
                PrintMsg("Нет категории: $category\n");
                $sqlin = "INSERT INTO $dle_category_db (name) VALUES ('$category')";
                $res = $database->exec($sqlin);
                $cat = $database->query($sql);
                $current_cat = $cat->fetch()["id"];
                PrintMsg("Категория создана\n");
            } else {
                PrintMsg("Категории нет и она не будет создана!\n");
            }
            $result .= $current_cat . ",";
        } catch (PDOException $e) {
            PrintErr($database->errorInfo());
        }
    }
    substr($result, 0, -1);
    return $result;
}

/**
 * Метод сохранения полученных из запроса книг в бд
 */
function SaveBooksFromPage($books)
{
    global $db_table;
    global $database;
    global $book_id;
    global $book_title;
    global $book_bio;
    global $book_cover;
    global $book_duration;
    global $book_rating;
    global $book_amount;
    global $book_plus18;
    global $book_plus16;
    global $book_with_music;
    global $book_not_finished;
    global $book_author;
    global $book_reader;
    global $book_series;
    global $book_genres;
    global $book_lang;
    global $book_sale_closed;
    global $book_publish_date;
    global $meta_translater;
    global $meta_copyright;
    global $meta_publisher;

    global $dle_author;
    global $dle_approve;
    global $dle_comm;
    global $dle_fields;
    global $dle_main;
    global $dle_alt;

    global $check_exist;
    global $save_images_to_server;

    foreach ($books["data"] as $book) {
        $current_book = new Book(
            $book["id"],
            $book["title"],
            $book["bio"],
            $book["cover"],
            $book["duration"],
            $book["rating"],
            $book["amount"],
            $book["plus_18"],
            $book["plus_16"],
            $book["with_music"],
            $book["not_finished"],
            $book["author_name"],
            $book["reader_name"],
            $book["series_name"],
            $book["genres"],
            $book["lang_name"],
            $book["publish_date"],
            $book["sale_closed"]
        );

        $current_meta = new Meta(
            $book['meta_data']["translate_author"],
            $book['meta_data']["copyright_holder"],
            $book['meta_data']["publisher"]
        );

        // Пропуск временно запрещенных к публикации книг
        if ($book['meta_data']["publisher"] == 'UGC')
            continue;
        if (!empty($book["sale_closed"]))
            continue;

        if ($current_book->plus18)
            $current_book->plus18 = 1;
        else
            $current_book->plus18 = 0;
        if ($current_book->plus16)
            $current_book->plus16 = 1;
        else
            $current_book->plus16 = 0;
        if ($current_book->with_music)
            $current_book->with_music = 1;
        else
            $current_book->with_music = 0;
        if ($current_book->not_finished)
            $current_book->not_finished = 1;
        else
            $current_book->not_finished = 0;
        if ($current_book->sale_closed)
            $current_book->sale_closed = 1;
        else
            $current_book->sale_closed = 0;

        PrintMsg("Add book with id " . $current_book->id . " and name " . $current_book->title . "\n");

        if (strripos($current_book->title, "'")) {
            $temp_title = $current_book->title;
            $current_book->title = str_replace("'", "\'", $temp_title);
            PrintMsg("Replaced title: " . $current_book->title);
        }

        if ($check_exist) {
            $check = "SELECT * FROM $db_table bt WHERE bt.$book_title = '$current_book->title';";
            try {
                $result = $database->query($check);
                if ($result->fetch())
                    continue;
            } catch (PDOException $e) {
                PrintErr($e->errorInfo());
                continue;
            }
        }

        $alt_name = str2url($current_book->title);

        $d = strtotime("now");
        $currentDate = date("Y-m-d h:i:s", $d);
        $categories = GetCategories($current_book->genres);

        if ($save_images_to_server) {
            $directoryWithFile = "./uploads/posts/biblio-books/" . $alt_name . ".jpg";
            $shortDirectoryWithFile = "biblio-books/" . $alt_name . ".jpg";
            if (!copy($current_book->cover, $directoryWithFile)) {
                PrintErr("Error with save image\n");
            }
        }

        $current_book->bio = str_replace("<p>", " ", $current_book->bio);
        $current_book->bio = str_replace("</p>", " ", $current_book->bio);
        $current_book->bio = str_replace("<br>", " ", $current_book->bio);
        $current_book->bio = str_replace("</ br>", " ", $current_book->bio);
        $current_book->bio = str_replace("<br />", " ", $current_book->bio);
        $current_book->bio = str_replace("&quot;", " ", $current_book->bio);
        $current_book->bio = str_replace("&hellip;", " ", $current_book->bio);
        $current_book->bio = str_replace("&mdash;", " ", $current_book->bio);
        $current_book->bio = str_replace("&nbsp;", " ", $current_book->bio);

        $sql = "INSERT INTO $db_table (
                $dle_author,$book_bio,$dle_fields,$book_title,$dle_alt,$dle_comm,$dle_main,$dle_approve,$book_genres,date
            ) VALUES (
                'admin', '$current_book->bio', '$book_id|$current_book->id||$book_amount|$current_book->amount||$book_plus16|$current_book->plus16||$book_plus18|$current_book->plus18||$book_with_music|$current_book->with_music||$book_author|$current_book->author||$book_cover|$current_book->cover||$book_reader|$current_book->reader||$meta_publisher|$current_meta->publisher||$book_series|$current_book->series||$meta_translater|$current_meta->translater', '$current_book->title', '$alt_name', 1, 1, 1,'$categories','$currentDate'
            );";

        try {
            $result = $database->exec($sql);
        } catch (PDOException $e) {
            PrintErr($e->errorInfo());
            continue;
        }

        PrintMsg("Done\n");
    }
}

/**
 * Функция получения всего каталога Biblio
 */
function GetBooksFromAllPages()
{
    PrintMsg("Getting books from all pages\n");
    global $last_page;
    global $current_page;

    NewDatabaseConnection();
    $last_page = (int) GetBooksFromPage()["last_page"];
    while ($current_page <= $last_page) {
        SaveBooksFromPage(GetBooksFromPage());
        PrintMsg("Pages: " . $current_page . "/" . $last_page . "\n");
    }

    PrintMsg("Completed!");
}

function rus2translit($string)
{
    $converter = array(
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ь' => '\'',
        'ы' => 'y',
        'ъ' => '\'',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',

        'А' => 'A',
        'Б' => 'B',
        'В' => 'V',
        'Г' => 'G',
        'Д' => 'D',
        'Е' => 'E',
        'Ё' => 'E',
        'Ж' => 'Zh',
        'З' => 'Z',
        'И' => 'I',
        'Й' => 'Y',
        'К' => 'K',
        'Л' => 'L',
        'М' => 'M',
        'Н' => 'N',
        'О' => 'O',
        'П' => 'P',
        'Р' => 'R',
        'С' => 'S',
        'Т' => 'T',
        'У' => 'U',
        'Ф' => 'F',
        'Х' => 'H',
        'Ц' => 'C',
        'Ч' => 'Ch',
        'Ш' => 'Sh',
        'Щ' => 'Sch',
        'Ь' => '\'',
        'Ы' => 'Y',
        'Ъ' => '\'',
        'Э' => 'E',
        'Ю' => 'Yu',
        'Я' => 'Ya',
    );
    return strtr($string, $converter);
}

function str2url($str)
{
    // переводим в транслит
    $str = rus2translit($str);
    // в нижний регистр
    $str = strtolower($str);
    // заменям все ненужное нам на "-"
    $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
    // удаляем начальные и конечные '-'
    $str = trim($str, "-");
    return $str;
}


/**
 * Функция для обновления книги (only_update)
 */
function UpdateBook($book)
{
    NewDatabaseConnection();

    global $db_table;
    global $database;
    global $book_id;
    global $book_title;
    global $book_bio;
    global $book_cover;
    global $book_duration;
    global $book_rating;
    global $book_amount;
    global $book_plus18;
    global $book_plus16;
    global $book_with_music;
    global $book_not_finished;
    global $book_author;
    global $book_reader;
    global $book_series;
    global $book_genres;
    global $book_lang;
    global $book_sale_closed;
    global $book_publish_date;
    global $meta_translater;
    global $meta_copyright;
    global $meta_publisher;
    global $dle_fields;

    $current_book = new Book(
        $book["id"],
        $book["title"],
        $book["bio"],
        $book["cover"],
        $book["duration"],
        $book["rating"],
        $book["amount"],
        $book["plus_18"],
        $book["plus_16"],
        $book["with_music"],
        $book["not_finished"],
        $book["author_name"],
        $book["reader_name"],
        $book["series_name"],
        $book["genres"],
        $book["lang_name"],
        $book["publish_date"],
        $book["sale_closed"]
    );

    $current_meta = new Meta(
        $book['meta_data']["translate_author"],
        $book['meta_data']["copyright_holder"],
        $book['meta_data']["publisher"]
    );

    $d = strtotime("now");
    $currentDate = date("Y-m-d h:i:s", $d);

    if (strripos($current_book->title, "'")) {
        $temp_title = $current_book->title;
        $current_book->title = str_replace("'", "\'", $temp_title);
    }

    $current_book->bio = str_replace("<p>", " ", $current_book->bio);
    $current_book->bio = str_replace("</p>", " ", $current_book->bio);
    $current_book->bio = str_replace("<br>", " ", $current_book->bio);
    $current_book->bio = str_replace("</ br>", " ", $current_book->bio);
    $current_book->bio = str_replace("<br />", " ", $current_book->bio);
    $current_book->bio = str_replace("&quot;", " ", $current_book->bio);
    $current_book->bio = str_replace("&hellip;", " ", $current_book->bio);
    $current_book->bio = str_replace("&mdash;", " ", $current_book->bio);
    $current_book->bio = str_replace("&nbsp;", " ", $current_book->bio);

    $sql = "UPDATE $db_table SET $book_bio = '$current_book->bio', $dle_fields = '$book_id|$current_book->id||$book_amount|$current_book->amount||$book_plus16|$current_book->plus16||$book_plus18|$current_book->plus18||$book_with_music|$current_book->with_music||$book_author|$current_book->author||$book_cover|$current_book->cover||$book_reader|$current_book->reader||$meta_publisher|$current_meta->publisher||$book_series|$current_book->series||$meta_translater|$current_meta->translater', $book_title = '$current_book->title', date = '$currentDate' WHERE $book_title = '$current_book->title'";

    try {
        $result = $database->exec($sql);
    } catch (PDOException $e) {
        PrintErr($e->errorInfo());
        continue;
    }
}

/**
 * Функция для удаления книги (only_update)
 */
function DeleteBook($book)
{
    NewDatabaseConnection();

    global $database;
    global $db_table;
    global $book_title;

    if (strripos($book['title'], "'")) {
        $temp_title = $book['title'];
        $book['title'] = str_replace("'", "\'", $temp_title);
    }
    $title = $book['title'];

    $sql = "DELETE FROM $db_table WHERE $book_title = '$title'"; // todo

    try {
        $result = $database->exec($sql);
    } catch (PDOException $e) {
        PrintErr($e->errorInfo());
        continue;
    }
}

/**
 * Функция для добавления новой книги (only_update)
 */
function AddBook($book)
{
    NewDatabaseConnection();

    global $db_table;
    global $database;
    global $book_id;
    global $book_title;
    global $book_bio;
    global $book_cover;
    global $book_duration;
    global $book_rating;
    global $book_amount;
    global $book_plus18;
    global $book_plus16;
    global $book_with_music;
    global $book_not_finished;
    global $book_author;
    global $book_reader;
    global $book_series;
    global $book_genres;
    global $book_lang;
    global $book_sale_closed;
    global $book_publish_date;
    global $meta_translater;
    global $meta_copyright;
    global $meta_publisher;

    global $dle_author;
    global $dle_approve;
    global $dle_comm;
    global $dle_fields;
    global $dle_main;
    global $dle_alt;

    global $save_images_to_server;

    $current_book = new Book(
        $book["id"],
        $book["title"],
        $book["bio"],
        $book["cover"],
        $book["duration"],
        $book["rating"],
        $book["amount"],
        $book["plus_18"],
        $book["plus_16"],
        $book["with_music"],
        $book["not_finished"],
        $book["author_name"],
        $book["reader_name"],
        $book["series_name"],
        $book["genres"],
        $book["lang_name"],
        $book["publish_date"],
        $book["sale_closed"]
    );

    $current_meta = new Meta(
        $book['meta_data']["translate_author"],
        $book['meta_data']["copyright_holder"],
        $book['meta_data']["publisher"]
    );

    // Пропуск временно запрещенных к публикации книг
    if ($book['meta_data']["publisher"] == 'UGC')
        continue;
    if (!empty($book["sale_closed"]))
        continue;

    if ($current_book->plus18)
        $current_book->plus18 = 1;
    else
        $current_book->plus18 = 0;
    if ($current_book->plus16)
        $current_book->plus16 = 1;
    else
        $current_book->plus16 = 0;
    if ($current_book->with_music)
        $current_book->with_music = 1;
    else
        $current_book->with_music = 0;
    if ($current_book->not_finished)
        $current_book->not_finished = 1;
    else
        $current_book->not_finished = 0;
    if ($current_book->sale_closed)
        $current_book->sale_closed = 1;
    else
        $current_book->sale_closed = 0;

    if (strripos($current_book->title, "'")) {
        $temp_title = $current_book->title;
        $current_book->title = str_replace("'", "\'", $temp_title);
    }

    $alt_name = "book-" . $current_book->id;

    $d = strtotime("now");
    $currentDate = date("Y-m-d h:i:s", $d);
    $categories = GetCategories($current_book->genres);

    if ($save_images_to_server) {
        $directoryWithFile = "./uploads/posts/biblio-books/" . $alt_name . ".jpg";
        $shortDirectoryWithFile = "biblio-books/" . $alt_name . ".jpg";
        if (!copy($current_book->cover, $directoryWithFile)) {
            PrintErr("Error with save image\n");
        }
    }

    $current_book->bio = str_replace("<p>", " ", $current_book->bio);
    $current_book->bio = str_replace("</p>", " ", $current_book->bio);
    $current_book->bio = str_replace("<br>", " ", $current_book->bio);
    $current_book->bio = str_replace("</ br>", " ", $current_book->bio);
    $current_book->bio = str_replace("<br />", " ", $current_book->bio);
    $current_book->bio = str_replace("&quot;", " ", $current_book->bio);
    $current_book->bio = str_replace("&hellip;", " ", $current_book->bio);
    $current_book->bio = str_replace("&mdash;", " ", $current_book->bio);
    $current_book->bio = str_replace("&nbsp;", " ", $current_book->bio);

    $sql = "INSERT INTO $db_table (
                $dle_author,$book_bio,$dle_fields,$book_title,$dle_alt,$dle_comm,$dle_main,$dle_approve,$book_genres,date
            ) VALUES (
                'admin', '$current_book->bio', '$book_id|$current_book->id||$book_amount|$current_book->amount||$book_plus16|$current_book->plus16||$book_plus18|$current_book->plus18||$book_with_music|$current_book->with_music||$book_author|$current_book->author||$book_cover|$current_book->cover||$book_reader|$current_book->reader||$meta_publisher|$current_meta->publisher||$book_series|$current_book->series||$meta_translater|$current_meta->translater', '$current_book->title', '$alt_name', 1, 1, 1,'$categories','$currentDate'
            );";

    try {
        $result = $database->exec($sql);
    } catch (PDOException $e) {
        PrintErr($e->errorInfo());
        continue;
    }
}

/**
 * Функция для отображения обычного сообщения
 */
function PrintMsg($msg)
{
    print_r("[msg]: $msg \n");
}

/**
 * Функция для отображения сообщения об ошибке
 */
function PrintErr($err)
{
    print_r("\n[err]: $err \n");
}

if (!$only_update)
    GetBooksFromAllPages();

?>
