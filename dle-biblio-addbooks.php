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
$db_table = "";  # Наименование таблицы в базе данных для добавления книг

# Поля базы данных #
$book_id = "";  # Поле идентификатора книги из Biblio
$book_title = "";  # Поле наименования книги из Biblio
$book_bio = "";  # Поле описания книги из Biblio
$book_cover = "";  # Поле обложки из Biblio
$book_duration = "";  # Поле длительности книги из Biblio
$book_rating = "";  # Поле рейтинга книги из Biblio
$book_amount = "";  # Поле цены книги из Biblio
$book_plus18 = "";  # Поле ограничения книги по возрасту из Biblio (18+)
$book_plus16 = "";  # Поле ограничения книги по возрасту из Biblio (16+)
$book_with_music = "";  # Поле обозначения книг с музыкой из Biblio
$book_not_finished = "";  # Поле обозначения неоконченности книги из Biblio
$book_author = "";  # Поле автора книги из Biblio
$book_reader = "";  # Поле читателя книги из Biblio
$book_series = "";  # Поле серии книги из Biblio
$book_genres = "";  # Поле жанра книги из Biblio
$book_lang = "";  # Поле языка книги из Biblio
$book_publish_date = "";  # Поле даты публикации книги из Biblio
$book_sale_closed = "";  # Поле доступности книги для продажи из Biblio

$meta_translater = "";  # Поле переводчика книги из Biblio
$meta_copyright = "";  # Поле лицензии книги из Biblio
$meta_publisher = "";  # Поле издателя книги из Biblio

# Дополнительные поля для DLE
$dle_author = "";
$dle_fields = "";
$dle_comm = "";
$dle_main = "";
$dle_approve = "";
$dle_alt = "";
$dle_category_db = "dle_category";
################################################################################

# Переменные запроса (не изменять)
$current_page = 0;  # Текущая страница
$last_page = 0;  # Последняя страница
$biblio_api_url = "https://api.bibliovk.ru/api/ref/data/catalog/full?page=";  # URL API
$database = new stdClass;

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

class Meta
{
    function __construct($translater, $copyright, $publisher)
    {
        $this->translater = $translater ?: "";
        $this->copyright = $copyright ?: "";
        $this->publisher = $publisher ?: "";
    }
}

function GetBooksFromPage()
{
    print_r("getting books\n");
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

function NewDatabaseConnection()
{
    print_r("connection to database\n");
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

function GetCategories($categories) {
    global $database;
    global $dle_category_db;

    $result = '';
    $cats = explode(", ", $categories);

    foreach ($cats as $category) {
        $sql = "SELECT id FROM $dle_category_db cat WHERE cat.name = '$category'";
        try{
            	global $result;
                $cat = $database->query($sql)->fetch();
                if ($cat){
                    $current_cat = $cat["id"];
                    print_r("категория $category\n");
                    print_r("id: $current_cat\n");
                } else {
                        print_r("нет категории\n");
                        $sqlin = "INSERT INTO $dle_category_db (name) VALUES ('$category')";
                        $res = $database->exec($sqlin);
                        $cat = $database->query($sql);
                        $current_cat = $cat->fetch()["id"];
                }
                $result.=$current_cat.",";
        } catch (PDOException $e) {
            print_r($database->errorInfo());
        }
    }
    substr($result, 0, -1);
    return $result;
}


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
        if ($current_book->plus18) $current_book->plus18 = 1; else $current_book->plus18 = 0;
        if ($current_book->plus16) $current_book->plus16 = 1; else $current_book->plus16 = 0;
        if ($current_book->with_music) $current_book->with_music = 1; else $current_book->with_music = 0;
        if ($current_book->not_finished) $current_book->not_finished = 1; else $current_book->not_finished = 0;
        if ($current_book->sale_closed) $current_book->sale_closed = 1; else $current_book->sale_closed = 0;

        print_r("Add book with id ".$current_book->id." and name ".$current_book->title."\n");

        if (strripos($current_book->title, "'")) {
            $temp_title = $current_book->title;
            $current_book->title = str_replace("'", "\'", $temp_title);
            print_r("Replaced title: ".$current_book->title);
        }

        $check = "SELECT * FROM $db_table bt WHERE bt.$book_title = '$current_book->title';";
        try {
            $result = $database->query($check);
            if ($result->fetch()) continue;
        } catch(PDOException $e) {
            print_r($e->errorInfo());
            continue;
        }

        $alt_name = "book-".$current_book->id;
        $agelimit = "0+";
        if ($current_book->plus18 == 1) {
            $agelimit = "18+";
        } else if ($current_book->plus16 == 1) {
            $agelimit = "16+";
        }

        $d = strtotime("now");
        $currentDate = date("Y-m-d h:i:s", $d);
        $categories = GetCategories($current_book->genres);

        $directoryWithFile = "./uploads/posts/biblio-books/".$alt_name.".jpg";
        $shortDirectoryWithFile = "biblio-books/".$alt_name.".jpg";
        if (!copy($current_book->cover, $directoryWithFile)){
            print_r("Error with save image\n");
        }

        $sql = "INSERT INTO $db_table (
                $dle_author,$book_bio,$dle_fields,$book_title,$dle_alt,$dle_comm,$dle_main,$dle_approve,$book_genres,full_story,keywords,date,category
            ) VALUES (
                'admin1', '$current_book->bio', 'format|Аудиокнига||name|$current_book->author||track_name|$current_book->title||book_reader|$current_book->reader||book_publisher|$current_meta->publisher||book_plus_18|$current_book->plus18||book_plus_16|$current_book->plus16||book_id|$current_book->id||poster|$shortDirectoryWithFile||text_pesen|$current_book->bio', '$current_book->title', '$alt_name', 1, 1, 1, '$current_book->genres','','','$currentDate','$categories'
            );";

        try {
            $result = $database->exec($sql);
        } catch(PDOException $e) {
            print_r($e->errorInfo());
            continue;
        }

        print_r("done\n");
    }
}

function GetBooksFromAllPages()
{
    print_r("getting books from all pages\n");
    global $last_page;
    global $current_page;

    NewDatabaseConnection();
    $last_page = (int)GetBooksFromPage()["last_page"];

    while ($current_page <= $last_page) {
        SaveBooksFromPage(GetBooksFromPage());
        print_r("Pages: ".$current_page."/".$last_page."\n");
    }

    print_r("Completed!");
}

GetBooksFromAllPages();

?>
