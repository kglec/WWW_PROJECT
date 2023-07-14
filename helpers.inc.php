<?php

if (!defined('IN_INDEX')) { exit("This file can only be included in index.php."); }

/*
 * Funkcja get_domain() nie przyjmuje żadnych argumentów i zwraca adres bieżącej domeny. Adres ten znajduje się
 * w zmiennej superglobalnej $_SERVER['HTTP_HOST']. Tablica $_SERVER zawiera wiele innych przydatnych zmiennych
 * środowiskowych, np. warto wiedzieć, że adres IP użytkownika znajduje się w $_SERVER['REMOTE_ADDR'].
 * Opis tablicy $_SERVER znajdziesz na stronie:
 * https://www.php.net/manual/en/reserved.variables.server.php
 */
function get_domain(): string {
    /*
     * Dane w tablicy $_SERVER nie zawsze można bezpiecznie wyświetlić na stronie, ponieważ część z nich pochodzi
     * od użytkownika (przeglądarki). W ten sposób użytkownicy strony mogliby potencjalnie wstrzykiwać w naszą stronę
     * złośliwy kod JavaScript (poczytaj o atakach XSS). Aby tego uniknąć zmienne należy oczyścić - w tym przypadku
     * użyto funkcji preg_replace, która za pomocą wyrażenia regularnego wyrzuca z ciągu znaków wszystko co nie jest
     * literami, cyframi i znakiem kropki. Warto zwrócić uwagę, że biblioteka szablonów Twig dodatkowo wykorzystuje
     * escapowanie danych (więcej o nim w pliku main.php), które rownież chroni nas przed tego typu atakami.
     */
    $domena = preg_replace('/[^a-zA-Z0-9\.]/', '', $_SERVER['HTTP_HOST']);
    return $domena;
}

/*
 * Klasa DB zadba o utworzenie połączenia do bazy danych podczas pierwszego wywołania metody "getInstance" i będzie
 * zwracać obiekt połączenia na żądanie z dowolnego miejsca aplikacji.
 */
class DB {
    private static $dbh = null; // tutaj będziemy przechowywać obiekt PDO

    public static function getInstance(): PDO {

        // Jeśli w zmiennej statycznej self::$dbh nie mamy jeszcze utworzonego połączenia do bazy danych to je tworzymy.
        if (!self::$dbh) {
            /*
             * Instrukcja try-catch występuje w wielu językach programowania. Służy ona do przechwycenia wyjątku (błędu)
             * rzuconego przez kod znajdujący się w środku bloku "try" oraz odpowiednią reakcję na rzucony wyjątek
             * w bloku "catch".
             */
            try {
                /*
                 * Tworzymy nowy obiekt wbudowanej w PHP klasy PDO, służącej do komunikacji z bazą danych, przekazując
                 * do jego konstruktora parametry połączenia do bazy danych. Obiekt PDO reprezentujący połączenie z bazą
                 * danych przypisujemy do zmiennej statycznej self::$dbh. Dzięki temu w dowolnym miejscu kodu możemy
                 * uzyskać połączenie do bazy danych za pomocą DB::getInstance().
                 * Za pomocą metody "setAttribute" ustawiamy na utworzonym obiekcie, aby PDO obsługiwało błędy za pomocą
                 * wyjątków (są tez inne tryby, na przykład zwracanie standardowych błędów PHP).
                 * PDO jest potężną biblioteką. Potrafi obsługiwać różne typy baz w uniwersalny sposób, dzięki temu
                 * zmiana oprogramowania serwera bazy danych nie musi oznaczać zmiany kodu/zapytań w PHP.
                 * Opis klasy PDO i jej metod znajdziesz na stronie: https://www.php.net/manual/en/class.pdo
                */
                self::$dbh = new PDO(
                    'mysql:host=' . CONFIG['db_host'] . ';dbname=' . CONFIG['db_name'] . ';charset=utf8mb4',
                    CONFIG['db_user'],
                    CONFIG['db_password']
                );
                self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                exit("Cannot connect to the database: " . $e->getMessage());
            }
        }
        // Zwracamy obiekt PDO, reprezentujący połączenie z bazą danych.
        return self::$dbh;
    }
}

/*
 * Klasa TwigHelper zadba o utworzenie obiektu biblioteki Twig podczas pierwszego wywołania metody "getInstance"
 * i będzie zwracać ten obiekt na żądanie z dowolnego miejsca aplikacji. Dodatkowo pomoże nam zautomatyzować proces
 * wyświetlania komunikatów w szablonach.
 */
class TwigHelper {
    private static $twig = null; // tutaj będziemy przechowywać obiekt Twiga
    private static $msg = []; // tutaj będziemy przechowywać komunikaty do wyświetlenia w szablonie base.html

    public static function getInstance(): \Twig\Environment {
        // Jeśli w zmiennej statycznej self::$twig nie mamy jeszcze utworzonego obiektu Twiga to go tworzymy.
        if (!self::$twig) {
            /*
             * Inicjalizujemy obiekt biblioteki Twig zgodnie z dokumentacją ze strony:
             * https://twig.symfony.com/doc/3.x/api.html
             * Obiekt przypisujemy do zmiennej statycznej self::$twig. Dzięki temu w dowolnym miejscu kodu możemy
             * uzyskać do niego dostęp za pomocą TwigSingleton::getInstance().
             */
            $twig_loader = new \Twig\Loader\FilesystemLoader('templates');
            self::$twig = new \Twig\Environment($twig_loader);
        }
        // Zwracamy obiekt Twiga
        return self::$twig;
    }

    /*
     * Klasa TwigHelper dostarczy nam jeszcze jednej przydatnej metody. W tablicy self::$msg trzymane będą
     * komunikaty, które będzie wyświetlać szablon base.html nad treścią podstrony. Możesz dodawać dowolne komunikaty
     * za pomocą TwigHelper::getInstance()->addMsg($text, $type). Przykłady użycia znajdziesz w pliku main.php.
     * Jako typ możesz przekazać ciąg tekstowy: success, info, error. Typ decyduje o kolorze tła komunikatu.
     */
    public static function addMsg(string $text, string $type): void {
        self::$msg[] = [
            'text' => $text,
            'type' => $type
        ];
    }

    // Metoda zwraca całą tablicę z komunikatami - będzie jej używać szablon base.html.
    public static function getMsg(): array {
        return self::$msg;
    }
}

// Konfigurujemy Twiga tak, aby w każdym szablonie była dostępna stała CONFIG, funkcja get_domain(),
// oraz metoda TwigHelper::getMsg (pod nazwą get_msg), bez konieczności ręcznego przekazywania ich podczas renderowania.
TwigHelper::getInstance()->addGlobal('CONFIG', CONFIG);
TwigHelper::getInstance()->addGlobal('_session', $_SESSION);
TwigHelper::getInstance()->addFunction(new \Twig\TwigFunction('get_domain', 'get_domain'));
TwigHelper::getInstance()->addFunction(new \Twig\TwigFunction('get_msg', 'TwigHelper::getMsg'));
