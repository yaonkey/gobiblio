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
$db_user = "root";  # Пользователь базы данных
$db_password = "";  # Пароль пользователя базы данных
$db_name = "";  # Наименование таблицы в базе данных для добавления книг
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
$book_sale_closed = false;  # Поле доступности книги для продажи из Biblio

$meta_translater = "";  # Поле переводчика книги из Biblio
$meta_copyright = "";  # Поле лицензии книги из Biblio
$meta_publisher = "";  # Поле издателя книги из Biblio

################################################################################

const per_page = 50;

# Переменные запроса (не изменять)
$current_page = 0;  # Текущая страница
$last_page = 0;  # Последняя страница
$biblio_api_url = "https://api.bibliovk.ru/api/ref/data/catalog/full?per_page=" . per_page . "&page=$current_page";  # URL API
$database = new StdClass;

class Book
{
    public $id = 0;
    public $title = "";
    public $bio = "";
    public $cover = "";
    public $duration = 0;
    public $rating = 0;
    public $amount = 0;
    public $plus18 = false;
    public $plus16 = false;
    public $with_music = false;
    public $not_finished = false;
    public $author = "";
    public $reader = "";
    public $series = "";
    public $genres = "";
    public $lang = "";
    public $publish_date = "";
    public $sale_closed = false;

    function __construct($id, $title, $bio, $cover, $duration, $rating, $amount, $plus18, $plus16, $with_music,
                         $not_finished, $author, $reader, $series, $genres, $lang, $publish_date, $sale_closed)
    {
        $this->id = $id;
        $this->title = $title;
        $this->bio = $bio;
        $this->cover = $cover;
        $this->duration = $duration;
        $this->rating = $rating;
        $this->amount = $amount;
        $this->plus18 = $plus18;
        $this->plus16 = $plus16;
        $this->with_music = $with_music;
        $this->not_finished = $not_finished;
        $this->author = $author;
        $this->reader = $reader;
        $this->series = $series;
        $this->genres = $genres;
        $this->lang = $lang;
        $this->publish_date = $publish_date;
        $this->sale_closed = $sale_closed;
    }
}

class Meta
{
    public $translater = "";
    public $copyright = "";
    public $publisher = "";

    function __construct($translater, $copyright, $publisher)
    {
        $this->translater = $translater;
        $this->copyright = $copyright;
        $this->publisher = $publisher;
    }
}

function GetBooksFromPage()
{
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

    $books = json_decode(file_get_contents($biblio_api_url), true, $ctx);
    $current_page++;

    return $books;
}

function NewDatabaseConnection()
{
    global $db_driver;
    global $db_name;
    global $db_host;
    global $db_user;
    global $db_password;
    global $database;

    try {
        $database = new PDO("$db_driver:host=$db_host;dbname=$db_name", $db_user, $db_password);
    } catch (PDOException $e) {
        die("PDO Error: " . $e->getMessage());
    }
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

    foreach ($books as $book) {
        $current_book = new Book(
            $book['data']["id"],
            $book['data']["title"],
            $book['data']["bio"],
            $book['data']["cover"],
            $book['data']["duration"],
            $book['data']["rating"],
            $book['data']["amount"],
            $book['data']["plus_18"],
            $book['data']["plus_16"],
            $book['data']["with_music"],
            $book['data']["not_finished"],
            $book['data']["author_name"],
            $book['data']["reader_name"],
            $book['data']["series_name"],
            $book['data']["genres"],
            $book['data']["lang"],
            $book['data']["publish_date"],
            $book['data']["sale_closed"]
        );

        $current_meta = new Meta(
            $book['meta_data']["translate_author"],
            $book['meta_data']["copyright_holder"],
            $book['meta_data']["publisher"]
        );

        $result = $database->exec("INSERT INTO $db_table (
            $book_id, $book_title, $book_bio, $book_cover, $book_duration, 
            $book_rating, $book_amount, $book_plus18, $book_plus16, $book_with_music, 
            $book_not_finished, $book_author, $book_reader, $book_series, $book_genres, $book_lang, 
            $book_publish_date, $book_sale_closed, $meta_translater, $meta_copyright, $meta_publisher) VALUES (
            $current_book->id, $current_book->title, $current_book->bio, $current_book->cover, $current_book->duration,
            $current_book->rating, $current_book->amount, $current_book->plus18, $current_book->plus16,
            $current_book->with_music, $current_book->not_finished, $current_book->author, $current_book->reader,
            $current_book->series, $current_book->genres, $current_book->lang, $current_book->publish_date,
            $current_book->sale_closed, $current_meta->translater, $current_meta->copyright, $current_meta->publisher)
            WHERE NOT EXISTS (SELECT * FROM $db_table WHERE $book_id = $current_book->id)");

        if (!$result) continue;
    }
}

function GetBooksFromAllPages()
{
    global $last_page;
    global $current_page;

    NewDatabaseConnection();
    $last_page = (int)GetBooksFromPage()["last_page"];

    while ($current_page <= $last_page) {
        SaveBooksFromPage(GetBooksFromPage());
    }
}

GetBooksFromAllPages();

?>