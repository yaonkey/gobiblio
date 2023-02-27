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
$referal_key = "";  # Ключ реферала

# База данных #
$db_driver = "mysql";  # Драйвер базы данных
$db_host = "localhost";  # Хост, на котором находится база данных
$db_port = "3306";  # Порт, на котором находится база данных
$db_user = "";  # Пользователь базы данных
$db_password = "";  # Пароль пользователя базы данных
$db_name = "";  # Наименование базы данных для добавления книг
$db_table = "wp-posts";  # Наименование таблицы в базе данных для добавления книг

# Поля базы данных #
$book_id = "id";  # Поле идентификатора книги из Biblio
$book_title = "post_title";  # Поле наименования книги из Biblio
$book_bio = "post_content";  # Поле описания книги из Biblio
$book_cover = "cover";  # Поле обложки из Biblio
$book_duration = "duration";  # Поле длительности книги из Biblio
$book_rating = "rating";  # Поле рейтинга книги из Biblio
$book_amount = "amount";  # Поле цены книги из Biblio
$book_plus18 = "plus_18";  # Поле ограничения книги по возрасту из Biblio (18+)
$book_plus16 = "plus_16";  # Поле ограничения книги по возрасту из Biblio (16+)
$book_with_music = "with_music";  # Поле обозначения книг с музыкой из Biblio
$book_not_finished = "not_finished";  # Поле обозначения неоконченности книги из Biblio
$book_author = "author";  # Поле автора книги из Biblio
$book_reader = "reader";  # Поле читателя книги из Biblio
$book_series = "series";  # Поле серии книги из Biblio
$book_genres = "genres";  # Поле жанра книги из Biblio
$book_lang = "lang";  # Поле языка книги из Biblio
$book_publish_date = "publish_date";  # Поле даты публикации книги из Biblio
$book_sale_closed = "sale_closed";  # Поле доступности книги для продажи из Biblio

$meta_translater = "translater";  # Поле переводчика книги из Biblio
$meta_copyright = "copyright";  # Поле лицензии книги из Biblio
$meta_publisher = "publisher";  # Поле издателя книги из Biblio
################################################################################

# Дополнительная настройка скрипта
$g_checkExist = false;  # Поле, отвечающее за проверку книг на наличие в БД реферала для исключения повторов

# Переменные запроса (не изменять)
$current_page = 0;  # Текущая страница
$last_page = 0;  # Последняя страница
$biblio_api_url = "https://api.bibliovk.ru/api/ref/data/catalog/full?page=";  # URL API
$database = new stdClass;

/**
 * Класс с информацией о книге
 * Требуется для получения информации
 * по API-запросу
 */
class Book
{
    function __construct($id, $title, $bio, $cover, $duration, $rating, $amount, $plus18, $plus16, $with_music,
                         $not_finished, $author, $reader, $series, $genres, $lang, $publish_date, $sale_closed)
    {
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
 * Класс с мета-информацией о книге
 * требуется для получения информации
 * по API-запросу
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
 * Метод для получения
 * книг с одной страницы
 * по API-запросу (50 книг/запрос)
 */
function GetBooksFromPage()
{
    print_r("Getting books\n");
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

    $books = json_decode(file_get_contents($biblio_api_url.$current_page, false, $ctx), true);
    $current_page++;

    return $books;
}

/**
 * Метод для подключения к базе данных реферала
 */
function NewDatabaseConnection()
{
    print_r("Connection to database\n");
    global $db_driver;
    global $db_name;
    global $db_host;
    global $db_user;
    global $db_password;
    global $database;

    try {
        $database = new PDO("$db_driver:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_password);
        print_r("Connection ok\n");
    } catch (PDOException $e) {
        die("PDO Error: " . $e->getMessage());
    }
}

/**
 * Метод для сохранения полученных книг со страницы в БД реферала
 * @param $books Полученные книги по API
 * @param $checkExist Поле, отвечающее за проверку книг на наличие в БД реферала для исключения повторов
 */
function SaveBooksFromPage($books, $checkExist=false)
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
    global $book_agelimit;
    global $meta_translater;
    global $meta_copyright;
    global $meta_publisher;

    global $post_author;

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
        if ($book['meta_data']["publisher"] == 'UGC') continue;
        if (!empty($book["sale_closed"])) continue;

        print_r("Add book with id $current_book->id, name $current_book->title and categories $categories\n");

        // Изменение названия в корректное для SQL-запроса
        if (strripos($current_book->title, "'")) {
            $temp_title = $current_book->title;
            $current_book->title = str_replace("'", "\'", $temp_title);
            print_r("Replaced title: ".$current_book->title);
        }

        // Проверка на наличие книги в базе
        if ($checkExist) {
            $check = "SELECT * FROM $db_table bt WHERE bt.$book_id = '$current_book->id';";
            try {
                $result = $database->query($check);
                if ($result->fetch()) continue;
            } catch(PDOException $e) {
                print_r($e->errorInfo());
                continue;
            }
        }

        $agelimit = "0+";
        if ($current_book->plus_18) $agelimit = "18+";
        if ($current_book->plus_16) $agelimit = "16+";

        // Удаление ненужных символов в описании книги
        $c_bio = str_replace("<p>&nbsp;</p>", "", $current_book->bio);
        $c_bio = str_replace("<p></p>", " ", $c_bio);
        $c_bio = str_replace("&nbsp;", "", $c_bio);
        $c_bio = str_replace("<p>", " ", $c_bio);
        $c_bio = str_replace("</p>", " ", $c_bio);
        $c_bio = str_replace("</br>", " ", $c_bio);
        $c_bio = str_replace("&quot;", " ", $c_bio);

        $c_rating = floatval($current_book->rating);
        $c_amount = floatval($current_book->amount);
        $c_with_music = boolval("$current_book->with_music")?1:0;
        $c_not_finished = boolval("$current_book->not_finished")?1:0;
        $c_sale_closed = boolval("$current_book->sale_closed")?1:0;

        $sql = "INSERT INTO $db_table (
                $book_id, $book_title, $book_bio, $book_cover, $book_duration, $book_rating, $book_amount, $book_with_music, $book_not_finished, $book_author, $book_reader, $book_series, $book_genres, $book_sale_closed, $book_agelimit, $meta_translater, $meta_copyright, $meta_publisher
            ) VALUES (
                $current_book->id, '$current_book->title', '$current_book->bio', '$current_book->cover', $current_book->duration, $c_rating, $c_amount, $c_with_music, $c_not_finished, '$current_book->author', '$current_book->reader', '$current_book->series', '$current_book->genres', $c_sale_closed, '$agelimit', '$current_meta->translater', '$current_meta->copyright', '$current_meta->publisher'
            );";

        try {
            $result = $database->exec($sql);
        } catch(PDOException $e) {
            print_r($e->errorInfo());
            continue;
        }
        print_r("Done\n");
    }
}

/**
 * Стартовый метод скрипта
 */
function GetBooksFromAllPages()
{
    print_r("Getting books from all pages\n");
    global $last_page;
    global $current_page;
    global $g_checkExist;

    NewDatabaseConnection();
    $last_page = (int)GetBooksFromPage()["last_page"];

    while ($current_page <= $last_page) {
        SaveBooksFromPage(GetBooksFromPage(), $g_checkExist);
        print_r("Pages: ".$current_page."/".$last_page."\n");
    }
    print_r("Completed!");
}

/**
 * Метод для перевода русского в транслит
 * @param $string Входящая строка
 * @return Транслит
 */
function rus2translit($string) {
    $converter = array(
        'а' => 'a',   'б' => 'b',   'в' => 'v',
        'г' => 'g',   'д' => 'd',   'е' => 'e',
        'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
        'и' => 'i',   'й' => 'y',   'к' => 'k',
        'л' => 'l',   'м' => 'm',   'н' => 'n',
        'о' => 'o',   'п' => 'p',   'р' => 'r',
        'с' => 's',   'т' => 't',   'у' => 'u',
        'ф' => 'f',   'х' => 'h',   'ц' => 'c',
        'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
        'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
        'э' => 'e',   'ю' => 'yu',  'я' => 'ya',

        'А' => 'A',   'Б' => 'B',   'В' => 'V',
        'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
        'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
        'И' => 'I',   'Й' => 'Y',   'К' => 'K',
        'Л' => 'L',   'М' => 'M',   'Н' => 'N',
        'О' => 'O',   'П' => 'P',   'Р' => 'R',
        'С' => 'S',   'Т' => 'T',   'У' => 'U',
        'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
        'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
        'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
        'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
    );
    return strtr($string, $converter);
}

/**
 * Метод для создания альтернативного имени
 * @param $str Входящая строка с исходным именем
 * @return Строка в транслите и с измененными символами
 */
function str2url($str) {
    // Переводим в транслит
    $str = rus2translit($str);
    // В нижний регистр
    $str = strtolower($str);
    // Заменям все ненужное нам на "-"
    $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
    // Удаляем начальные и конечные '-'
    $str = trim($str, "-");
    return $str;
}

// Вызов стартового метода
GetBooksFromAllPages();
?>
