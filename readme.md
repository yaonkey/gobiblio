# Добавление книг при помощи скрипта

## Подготовка

1. Создать бекап сайта и базы данных
2. Открыть скрипт `{cms}-biblio-addbooks.php`
3. Изменить конфигурацию (подробнее в пункте "Конфигурирование")
4. Переместить скрипт на сервер с сайтом
5. Запустить скрипт, используя SSH-подключение и команду `php {cms}-biblio-addbooks.php`

Не забудьте очистить кеш!

## Конфигурирование

Комментарии к каждому пункту заполнения информации в скрипте присутствуют.
Все поля из скрипта должны также присутствовать и в информации о книге в админке, иначе не сработает запрос к базе данных, либо можно подкорректировать SQL-запрос в скрипте, если какие-то из полей не требуют заполнения.

* Первым делом должна быть заполнена переменная с ключом реферала `$referal_key`
* Требуется заполнить информацию для подключения к базе данных
* Требуется заполнить информацию, содержащую поля базы данных

# Дополнительная информация

Если Вы собираетесь добавлять контент вручную или уже имеете в каталоге сайта книги Biblio, то можно использовать PHP-код (представлен ниже), позволяющий получать ID книги из системы Biblio в автоматическом режиме при загрузке страницы, посредством API-запроса. Стоит обратить внимание на то, что наименования книг должны в точности соответствовать таковым из системы Biblio (bibliovk.ru).

### PHP-код для автоматического получения ID книги из названия+автора

```php
<?php
function getBookId() {
   $url = 'https://api.bibliovk.ru/api/ref/find-book?';
      $opts = [
               "http" => [
                  "method" => "GET",
                  "header" => "Content-Type: application/json\r\n" .
                     "X-Biblio-Auth: Bearer $referal_key\r\n"
               ]
         ];
   $ctx = stream_context_create($opts);
   $books = json_decode(file_get_contents($url."title=".urlencode($title)."&author=".urlencode($author), false, $ctx), true);
   return $books['data'][0]['id'];
}
```

# Обновление контента с использованием скрипта

Для переключения режима работы скрипта требуется изменить переменную `$only_update` на значение `true`. После этого страница `biblio-content-update.php` сможет использовать функции скрипта лишь для обновления контента при помощи callback'ов с серверов Biblio.

В файле `biblio-content-update.php` требуется изменить переменную `$script_path`, которая отвечает за путь до скрипта, чтобы импортировать функции из скрипта для обновления контента.
