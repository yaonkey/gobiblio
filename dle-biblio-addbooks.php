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
$db_table = "dle_post";  # Наименование таблицы в базе данных для добавления книг

# Поля базы данных #
$book_id = "book_id";  # Поле идентификатора книги из Biblio
$book_title = "title";  # Поле наименования книги из Biblio
$book_bio = "short_story";  # Поле описания книги из Biblio
$book_cover = "image";  # Поле обложки из Biblio
$book_duration = "time";  # Поле длительности книги из Biblio
$book_rating = "book_rating";  # Поле рейтинга книги из Biblio
$book_amount = "book_amount";  # Поле цены книги из Biblio
$book_plus18 = "plus_18";  # Поле ограничения книги по возрасту из Biblio (18+)
$book_plus16 = "plus_16";  # Поле ограничения книги по возрасту из Biblio (16+)
$book_with_music = "book_with_music";  # Поле обозначения книг с музыкой из Biblio
$book_not_finished = "book_not_finished";  # Поле обозначения неоконченности книги из Biblio
$book_author = "author";  # Поле автора книги из Biblio
$book_reader = "reader";  # Поле читателя книги из Biblio
$book_series = "series";  # Поле серии книги из Biblio
$book_genres = "genres";  # Поле жанра книги из Biblio
$book_lang = "lang";  # Поле языка книги из Biblio
$book_publish_date = "publish_date";  # Поле даты публикации книги из Biblio
$book_sale_closed = "sale_closed";  # Поле доступности книги для продажи из Biblio

$meta_translater = "book_translater";  # Поле переводчика книги из Biblio
$meta_copyright = "book_copyright";  # Поле лицензии книги из Biblio
$meta_publisher = "book_publisher";  # Поле издателя книги из Biblio

# Дополнительные поля для DLE
$dle_author = "autor";  # Поле автора публикации
$dle_fields = "xfields";  # Поле дополнительных полей записи DLE
$dle_comm = "allow_comm";  # Поле доступности комментариев
$dle_main = "allow_main";
$dle_approve = "approve";  # Поле доступности публикации
$dle_alt = "alt_name";  # Поле альтернативного имени
$dle_category_db = "dle_category";  # Поле категорий DLE
################################################################################

# Дополнительная настройка скрипта
$g_createNewCategories = false;  # Поле, отвечающее за создании категории, если таковой не найдено в БД рефрала
$g_checkExist = true;  # Поле, отвечающее за проверку книг на наличие в БД реферала для исключения повторов
$g_fillBiblioId = false;  # Поле, отвечающее за добавление к уже существующим книгам ID, если они есть в системе Biblio (может работать некорректно и не работает, если $checkExist=false)
$g_saveImagesToFS = true;  # Поле, отвечающее за сохранение изображений книги в файловую систему, если отключено - будет сохранена ссылка на изображение

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
 * Требуется для получения информации
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

/**
 * Метод для подключения к базе данных реферала
 */
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

/**
 * Метод для получения ID категорий из существующих в БД реферала
 * Если категории не существует, то ее можно создать
 * @param $categories Список категорий, к которым относится книга
 * @param $createNewCategories Поле, отвечающее за создании категории, если таковой не найдено в БД рефрала
 * @return список id категорий
 */
function GetCategories($categories, $createNewCategories=false) {
    global $database;
    global $dle_category_db;

    $result = '';
    $cats = explode(", ", $categories);

    foreach ($cats as $category) {
        $sql = "SELECT id FROM $dle_category_db cat WHERE cat.name LIKE '%$category%'";
        try{
            $cat = $database->query($sql)->fetch(PDO::FETCH_ASSOC);
            if ($cat){
                $current_cat = $cat["id"];
            } else if ($createNewCategories){
                print_r("нет категории\n");
                $alt = str2url($category);
                $metacat = "Аудиокниги жанра $category бесплатно на нашем сайте";
                $metadescrcat = "Ищите аудиокниги жанра $category? Отличная подборка аудиокниг предоставлена на нашем сайте.";
                $sqlin = "INSERT INTO $dle_category_db (parentid, posi, name, alt_name, active, enable_dzen, enable_turbo, show_sub, allow_rss, disable_search, disable_main, disable_rating, disable_comments, rating_type, metatitle, descr) VALUES (0, 0, '$category', '$alt', 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, '$metacat', '$metadescrcat');";
                $database->exec($sqlin);

                $cat = $database->query($sql);
                $current_cat = $cat->fetch(PDO::FETCH_ASSOC)["id"];
            }
            $result.=$current_cat.",";
        } catch (PDOException $e) {
            print_r($database->errorInfo());
        }
    }
    if ($result != '') substr($result, 0, -1);
    return $result;
}

/**
 * Метод для сохранения полученных книг со страницы в БД реферала
 * @param $books Полученные книги по API
 * @param $checkExist Поле, отвечающее за проверку книг на наличие в БД реферала для исключения повторов
 * @param $fillBiblioId Поле, отвечающее за добавление к уже существующим книгам ID, если они есть в системе Biblio (может работать некорректно)
 */
function SaveBooksFromPage($books, $checkExist=false, $fillBiblioId=false, $saveImagesToFS=false)
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

    global $g_createNewCategories;

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

        // Изменение нулевых полей для SQL-запроса
        if ($current_book->plus18) $current_book->plus18 = 1; else $current_book->plus18 = 0;
        if ($current_book->plus16) $current_book->plus16 = 1; else $current_book->plus16 = 0;
        if ($current_book->with_music) $current_book->with_music = 1; else $current_book->with_music = 0;
        if ($current_book->not_finished) $current_book->not_finished = 1; else $current_book->not_finished = 0;
        if ($current_book->sale_closed) $current_book->sale_closed = 1; else $current_book->sale_closed = 0;

        // Получение категорий или создание новых
        $categories = GetCategories($current_book->genres, $g_createNewCategories);

        print_r("Add book with id $current_book->id, name $current_book->title and categories $categories\n");

        // Изменение названия в корректное для SQL-запроса
        if (strripos($current_book->title, "'")) {
            $temp_title = $current_book->title;
            $current_book->title = str_replace("'", "\'", $temp_title);
            print_r("Replaced title: ".$current_book->title);
        }

        if ($checkExist) {
            $check = "SELECT * FROM $db_table bt WHERE bt.$book_title LIKE '%$current_book->title%';";
            try {
                $result = $database->query($check);
                if ($result->fetch()) {
                    if ($fillBiblioId) {
                        $temp_id = $result->fetch(PDO::FETCH_ASSOC)['id'];
                        $temp_xfields = $result->fetch(PDO::FETCH_ASSOC)['xfields'];
                        if(strpos($temp_xfields, "book_id")) continue;
                        $temp = $temp_xfields . "||book_id|$current_book->id";
                        $query = "UPDATE dle_post SET xfields=$temp WHERE id=$temp_id;";
                        try {
                            $result = $database->exec($query);
                        } catch(PDOException $e) {
                            print_r($e->errorInfo());
                        }
                    }
                    continue;
                }
            } catch(PDOException $e) {
                print_r($e->errorInfo());
                continue;
            }
        }

        $alt_name = str2url($current_book->title);
        $currentDate = date("Y-m-d h:i:s", strtotime("now"));

        $c_book_cover = $current_book->cover;
        if ($saveImagesToFS) {
            $directoryWithFile = "./uploads/posts/biblio-books/".$alt_name.".jpg";
            $c_book_cover = "biblio-books/".$alt_name.".jpg";
            if (!copy($current_book->cover, $directoryWithFile)){
                print_r("Error with save image\n");
            }
        }

        $metatitle = "Слушать аудиокнигу $current_book->title $current_book->author онлайн без регистрации";
        $descr = "аудиокнига $current_book->title слушать онлайн,  $current_book->title";

        $c_duration = gmdate("H:i:s", $current_book->duration);
        $c_dur = "$c_duration";

        // Удаление ненужных символов в описании книги
        $c_bio = str_replace("<p>&nbsp;</p>", "", $current_book->bio);
        $c_bio = str_replace("<p></p>", " ", $c_bio);
        $c_bio = str_replace("&nbsp;", "", $c_bio);
        $c_bio = str_replace("<p>", " ", $c_bio);
        $c_bio = str_replace("</p>", " ", $c_bio);
        $c_bio = str_replace("</br>", " ", $c_bio);
        $c_bio = str_replace("&quot;", " ", $c_bio);

        $sql = "INSERT INTO $db_table (
                $dle_author,$book_bio,$dle_fields,$book_title,$dle_alt,$dle_comm,$dle_main,$dle_approve,keywords,category,metatitle,descr,date
            ) VALUES (
                'biblio', '$c_bio', '$book_title|$current_book->title||$book_lang|$current_book->lang||$book_author|$current_book->author||$book_duration|$c_dur||$book_reader|$current_book->reader||$meta_publisher|$current_meta->publisher||$book_plus18|$current_book->plus_18||$book_plus16|$current_book->plus_16||$book_id|$current_book->id||$book_cover|$c_book_cover||$book_amount|$current_book->amount||$book_with_music|$current_book->with_music||$book_not_finished|$current_book->not_finished||$meta_translater|$current_meta->translater||$meta_copyright|$current_meta->copyright||$book_series|$current_book->series||$book_rating|$current_book->rating||$book_sale_closed|$current_book->sale_closed||$book_publish_date|$current_book->publish_date||', '$current_book->title', '$alt_name', 1, 1, 0,'','$categories','$metatitle', '$descr', '$currentDate'
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

/**
 * Стартовый метод скрипта
 */
function GetBooksFromAllPages()
{
    print_r("Getting books from all pages\n");
    global $last_page;
    global $current_page;
    global $g_checkExist;
    global $g_fillBiblioId;
    global $g_saveImagesToFS;

    NewDatabaseConnection();
    $last_page = (int)GetBooksFromPage()["last_page"];

    while ($current_page <= $last_page) {
        SaveBooksFromPage(GetBooksFromPage(), $g_checkExist, $g_fillBiblioId, $g_saveImagesToFS);
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
