<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Pole :attribute musi zostac zaakceptowane.',
    'accepted_if' => 'Pole :attribute musi zostac zaakceptowane, gdy :other ma wartosc :value.',
    'active_url' => 'Pole :attribute musi byc prawidlowym adresem URL.',
    'after' => 'Pole :attribute musi byc data pozniejsza niz :date.',
    'after_or_equal' => 'Pole :attribute musi byc data rowna lub pozniejsza niz :date.',
    'alpha' => 'Pole :attribute moze zawierac wylacznie litery.',
    'alpha_dash' => 'Pole :attribute moze zawierac wylacznie litery, cyfry, myslniki i podkreslenia.',
    'alpha_num' => 'Pole :attribute moze zawierac wylacznie litery i cyfry.',
    'any_of' => 'Pole :attribute jest nieprawidlowe.',
    'array' => 'Pole :attribute musi byc tablica.',
    'ascii' => 'Pole :attribute moze zawierac wylacznie jednobajtowe znaki alfanumeryczne i symbole.',
    'before' => 'Pole :attribute musi byc data wczesniejsza niz :date.',
    'before_or_equal' => 'Pole :attribute musi byc data rowna lub wczesniejsza niz :date.',
    'between' => [
        'array' => 'Pole :attribute musi zawierac od :min do :max elementow.',
        'file' => 'Plik :attribute musi miec rozmiar od :min do :max kilobajtow.',
        'numeric' => 'Pole :attribute musi miec wartosc od :min do :max.',
        'string' => 'Pole :attribute musi miec od :min do :max znakow.',
    ],
    'boolean' => 'Pole :attribute musi miec wartosc prawda lub falsz.',
    'can' => 'Pole :attribute zawiera niedozwolona wartosc.',
    'confirmed' => 'Potwierdzenie pola :attribute nie jest zgodne.',
    'contains' => 'Pole :attribute nie zawiera wymaganej wartosci.',
    'current_password' => 'Haslo jest nieprawidlowe.',
    'date' => 'Pole :attribute musi byc prawidlowa data.',
    'date_equals' => 'Pole :attribute musi byc data rowna :date.',
    'date_format' => 'Pole :attribute musi byc zgodne z formatem :format.',
    'decimal' => 'Pole :attribute musi miec :decimal miejsc dziesietnych.',
    'declined' => 'Pole :attribute musi zostac odrzucone.',
    'declined_if' => 'Pole :attribute musi zostac odrzucone, gdy :other ma wartosc :value.',
    'different' => 'Pola :attribute i :other musza sie roznic.',
    'digits' => 'Pole :attribute musi miec :digits cyfr.',
    'digits_between' => 'Pole :attribute musi miec od :min do :max cyfr.',
    'dimensions' => 'Pole :attribute ma nieprawidlowe wymiary obrazu.',
    'distinct' => 'Pole :attribute zawiera zduplikowana wartosc.',
    'doesnt_contain' => 'Pole :attribute nie moze zawierac zadnej z nastepujacych wartosci: :values.',
    'doesnt_end_with' => 'Pole :attribute nie moze konczyc sie jednym z nastepujacych: :values.',
    'doesnt_start_with' => 'Pole :attribute nie moze rozpoczynac sie jednym z nastepujacych: :values.',
    'email' => 'Pole :attribute musi byc prawidlowym adresem e-mail.',
    'encoding' => 'Pole :attribute musi byc zakodowane w :encoding.',
    'ends_with' => 'Pole :attribute musi konczyc sie jednym z nastepujacych: :values.',
    'enum' => 'Wybrana wartosc dla :attribute jest nieprawidlowa.',
    'exists' => 'Wybrana wartosc dla :attribute jest nieprawidlowa.',
    'extensions' => 'Pole :attribute musi miec jedno z nastepujacych rozszen: :values.',
    'file' => 'Pole :attribute musi byc plikiem.',
    'filled' => 'Pole :attribute musi miec wartosc.',
    'gt' => [
        'array' => 'Pole :attribute musi zawierac wiecej niz :value elementow.',
        'file' => 'Plik :attribute musi byc wiekszy niz :value kilobajtow.',
        'numeric' => 'Pole :attribute musi byc wieksze niz :value.',
        'string' => 'Pole :attribute musi miec wiecej niz :value znakow.',
    ],
    'gte' => [
        'array' => 'Pole :attribute musi zawierac co najmniej :value elementow.',
        'file' => 'Plik :attribute musi miec co najmniej :value kilobajtow.',
        'numeric' => 'Pole :attribute musi byc rowne lub wieksze od :value.',
        'string' => 'Pole :attribute musi miec co najmniej :value znakow.',
    ],
    'hex_color' => 'Pole :attribute musi byc prawidlowym kolorem szesnastkowym.',
    'image' => 'Pole :attribute musi byc obrazem.',
    'in' => 'Wybrana wartosc dla :attribute jest nieprawidlowa.',
    'in_array' => 'Pole :attribute musi istniec w :other.',
    'in_array_keys' => 'Pole :attribute musi zawierac co najmniej jeden z nastepujacych kluczy: :values.',
    'integer' => 'Pole :attribute musi byc liczba calkowita.',
    'ip' => 'Pole :attribute musi byc prawidlowym adresem IP.',
    'ipv4' => 'Pole :attribute musi byc prawidlowym adresem IPv4.',
    'ipv6' => 'Pole :attribute musi byc prawidlowym adresem IPv6.',
    'json' => 'Pole :attribute musi byc prawidlowym ciagiem JSON.',
    'list' => 'Pole :attribute musi byc lista.',
    'lowercase' => 'Pole :attribute moze zawierac wylacznie male litery.',
    'lt' => [
        'array' => 'Pole :attribute musi zawierac mniej niz :value elementow.',
        'file' => 'Plik :attribute musi byc mniejszy niz :value kilobajtow.',
        'numeric' => 'Pole :attribute musi byc mniejsze niz :value.',
        'string' => 'Pole :attribute musi miec mniej niz :value znakow.',
    ],
    'lte' => [
        'array' => 'Pole :attribute nie moze zawierac wiecej niz :value elementow.',
        'file' => 'Plik :attribute nie moze byc wiekszy niz :value kilobajtow.',
        'numeric' => 'Pole :attribute musi byc rowne lub mniejsze od :value.',
        'string' => 'Pole :attribute nie moze miec wiecej niz :value znakow.',
    ],
    'mac_address' => 'Pole :attribute musi byc prawidlowym adresem MAC.',
    'max' => [
        'array' => 'Pole :attribute nie moze zawierac wiecej niz :max elementow.',
        'file' => 'Plik :attribute nie moze byc wiekszy niz :max kilobajtow.',
        'numeric' => 'Pole :attribute nie moze byc wieksze niz :max.',
        'string' => 'Pole :attribute nie moze miec wiecej niz :max znakow.',
    ],
    'max_digits' => 'Pole :attribute nie moze miec wiecej niz :max cyfr.',
    'mimes' => 'Pole :attribute musi byc plikiem typu: :values.',
    'mimetypes' => 'Pole :attribute musi byc plikiem typu: :values.',
    'min' => [
        'array' => 'Pole :attribute musi zawierac co najmniej :min elementow.',
        'file' => 'Plik :attribute musi miec co najmniej :min kilobajtow.',
        'numeric' => 'Pole :attribute musi byc rowne lub wieksze od :min.',
        'string' => 'Pole :attribute musi miec co najmniej :min znakow.',
    ],
    'min_digits' => 'Pole :attribute musi miec co najmniej :min cyfr.',
    'missing' => 'Pole :attribute nie moze byc obecne.',
    'missing_if' => 'Pole :attribute nie moze byc obecne, gdy :other ma wartosc :value.',
    'missing_unless' => 'Pole :attribute nie moze byc obecne, chyba ze :other ma wartosc :value.',
    'missing_with' => 'Pole :attribute nie moze byc obecne, gdy :values jest obecne.',
    'missing_with_all' => 'Pole :attribute nie moze byc obecne, gdy :values sa obecne.',
    'multiple_of' => 'Pole :attribute musi byc wielokrotnoscia :value.',
    'not_in' => 'Wybrana wartosc dla :attribute jest nieprawidlowa.',
    'not_regex' => 'Format pola :attribute jest nieprawidlowy.',
    'numeric' => 'Pole :attribute musi byc liczba.',
    'password' => [
        'letters' => 'Pole :attribute musi zawierac co najmniej jedna litere.',
        'mixed' => 'Pole :attribute musi zawierac co najmniej jedna wielka i jedna mala litere.',
        'numbers' => 'Pole :attribute musi zawierac co najmniej jedna cyfre.',
        'symbols' => 'Pole :attribute musi zawierac co najmniej jeden znak specjalny.',
        'uncompromised' => 'Podana wartosc :attribute pojawila sie w wycieku danych. Prosze wybrac inna wartosc :attribute.',
    ],
    'present' => 'Pole :attribute musi byc obecne.',
    'present_if' => 'Pole :attribute musi byc obecne, gdy :other ma wartosc :value.',
    'present_unless' => 'Pole :attribute musi byc obecne, chyba ze :other ma wartosc :value.',
    'present_with' => 'Pole :attribute musi byc obecne, gdy :values jest obecne.',
    'present_with_all' => 'Pole :attribute musi byc obecne, gdy :values sa obecne.',
    'prohibited' => 'Pole :attribute jest niedozwolone.',
    'prohibited_if' => 'Pole :attribute jest niedozwolone, gdy :other ma wartosc :value.',
    'prohibited_if_accepted' => 'Pole :attribute jest niedozwolone, gdy :other zostalo zaakceptowane.',
    'prohibited_if_declined' => 'Pole :attribute jest niedozwolone, gdy :other zostalo odrzucone.',
    'prohibited_unless' => 'Pole :attribute jest niedozwolone, chyba ze :other znajduje sie w :values.',
    'prohibits' => 'Pole :attribute nie pozwala na obecnosc :other.',
    'regex' => 'Format pola :attribute jest nieprawidlowy.',
    'required' => 'Pole :attribute jest wymagane.',
    'required_array_keys' => 'Pole :attribute musi zawierac wpisy dla nastepujacych kluczy: :values.',
    'required_if' => 'Pole :attribute jest wymagane, gdy :other ma wartosc :value.',
    'required_if_accepted' => 'Pole :attribute jest wymagane, gdy :other zostalo zaakceptowane.',
    'required_if_declined' => 'Pole :attribute jest wymagane, gdy :other zostalo odrzucone.',
    'required_unless' => 'Pole :attribute jest wymagane, chyba ze :other znajduje sie w :values.',
    'required_with' => 'Pole :attribute jest wymagane, gdy :values jest obecne.',
    'required_with_all' => 'Pole :attribute jest wymagane, gdy :values sa obecne.',
    'required_without' => 'Pole :attribute jest wymagane, gdy :values nie jest obecne.',
    'required_without_all' => 'Pole :attribute jest wymagane, gdy zadne z :values nie jest obecne.',
    'same' => 'Pola :attribute i :other musza byc zgodne.',
    'size' => [
        'array' => 'Pole :attribute musi zawierac dokladnie :size elementow.',
        'file' => 'Plik :attribute musi miec rozmiar :size kilobajtow.',
        'numeric' => 'Pole :attribute musi miec wartosc :size.',
        'string' => 'Pole :attribute musi miec dokladnie :size znakow.',
    ],
    'starts_with' => 'Pole :attribute musi rozpoczynac sie jednym z nastepujacych: :values.',
    'string' => 'Pole :attribute musi byc ciagiem znakow.',
    'timezone' => 'Pole :attribute musi byc prawidlowa strefa czasowa.',
    'unique' => 'Podana wartosc pola :attribute jest juz zajeta.',
    'uploaded' => 'Przeslanie pliku :attribute nie powiodlo sie.',
    'uppercase' => 'Pole :attribute moze zawierac wylacznie wielkie litery.',
    'url' => 'Pole :attribute musi byc prawidlowym adresem URL.',
    'ulid' => 'Pole :attribute musi byc prawidlowym identyfikatorem ULID.',
    'uuid' => 'Pole :attribute musi byc prawidlowym identyfikatorem UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
