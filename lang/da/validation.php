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

    'accepted' => 'Feltet :attribute skal accepteres.',
    'accepted_if' => 'Feltet :attribute skal accepteres, når :other er :value.',
    'active_url' => 'Feltet :attribute skal være en gyldig URL.',
    'after' => 'Feltet :attribute skal være en dato efter :date.',
    'after_or_equal' => 'Feltet :attribute skal være en dato efter eller lig med :date.',
    'alpha' => 'Feltet :attribute må kun indeholde bogstaver.',
    'alpha_dash' => 'Feltet :attribute må kun indeholde bogstaver, tal, bindestreger og understregninger.',
    'alpha_num' => 'Feltet :attribute må kun indeholde bogstaver og tal.',
    'any_of' => 'Feltet :attribute er ugyldigt.',
    'array' => 'Feltet :attribute skal være et array.',
    'ascii' => 'Feltet :attribute må kun indeholde enkeltbyte alfanumeriske tegn og symboler.',
    'before' => 'Feltet :attribute skal være en dato før :date.',
    'before_or_equal' => 'Feltet :attribute skal være en dato før eller lig med :date.',
    'between' => [
        'array' => 'Feltet :attribute skal have mellem :min og :max elementer.',
        'file' => 'Feltet :attribute skal være mellem :min og :max kilobytes.',
        'numeric' => 'Feltet :attribute skal være mellem :min og :max.',
        'string' => 'Feltet :attribute skal være mellem :min og :max tegn.',
    ],
    'boolean' => 'Feltet :attribute skal være sand eller falsk.',
    'can' => 'Feltet :attribute indeholder en ikke-tilladt værdi.',
    'confirmed' => 'Bekræftelsen af feltet :attribute stemmer ikke overens.',
    'contains' => 'Feltet :attribute mangler en påkrævet værdi.',
    'current_password' => 'Adgangskoden er forkert.',
    'date' => 'Feltet :attribute skal være en gyldig dato.',
    'date_equals' => 'Feltet :attribute skal være en dato lig med :date.',
    'date_format' => 'Feltet :attribute skal matche formatet :format.',
    'decimal' => 'Feltet :attribute skal have :decimal decimalpladser.',
    'declined' => 'Feltet :attribute skal afvises.',
    'declined_if' => 'Feltet :attribute skal afvises, når :other er :value.',
    'different' => 'Feltet :attribute og :other skal være forskellige.',
    'digits' => 'Feltet :attribute skal være :digits cifre.',
    'digits_between' => 'Feltet :attribute skal være mellem :min og :max cifre.',
    'dimensions' => 'Feltet :attribute har ugyldige billeddimensioner.',
    'distinct' => 'Feltet :attribute har en duplikeret værdi.',
    'doesnt_contain' => 'Feltet :attribute må ikke indeholde nogen af følgende: :values.',
    'doesnt_end_with' => 'Feltet :attribute må ikke slutte med en af følgende: :values.',
    'doesnt_start_with' => 'Feltet :attribute må ikke starte med en af følgende: :values.',
    'email' => 'Feltet :attribute skal være en gyldig e-mailadresse.',
    'encoding' => 'Feltet :attribute skal være kodet i :encoding.',
    'ends_with' => 'Feltet :attribute skal slutte med en af følgende: :values.',
    'enum' => 'Den valgte :attribute er ugyldig.',
    'exists' => 'Den valgte :attribute er ugyldig.',
    'extensions' => 'Feltet :attribute skal have en af følgende filtypenavne: :values.',
    'file' => 'Feltet :attribute skal være en fil.',
    'filled' => 'Feltet :attribute skal have en værdi.',
    'gt' => [
        'array' => 'Feltet :attribute skal have mere end :value elementer.',
        'file' => 'Feltet :attribute skal være større end :value kilobytes.',
        'numeric' => 'Feltet :attribute skal være større end :value.',
        'string' => 'Feltet :attribute skal være større end :value tegn.',
    ],
    'gte' => [
        'array' => 'Feltet :attribute skal have :value elementer eller flere.',
        'file' => 'Feltet :attribute skal være større end eller lig med :value kilobytes.',
        'numeric' => 'Feltet :attribute skal være større end eller lig med :value.',
        'string' => 'Feltet :attribute skal være større end eller lig med :value tegn.',
    ],
    'hex_color' => 'Feltet :attribute skal være en gyldig hexadecimal farve.',
    'image' => 'Feltet :attribute skal være et billede.',
    'in' => 'Den valgte :attribute er ugyldig.',
    'in_array' => 'Feltet :attribute skal eksistere i :other.',
    'in_array_keys' => 'Feltet :attribute skal indeholde mindst én af følgende nøgler: :values.',
    'integer' => 'Feltet :attribute skal være et heltal.',
    'ip' => 'Feltet :attribute skal være en gyldig IP-adresse.',
    'ipv4' => 'Feltet :attribute skal være en gyldig IPv4-adresse.',
    'ipv6' => 'Feltet :attribute skal være en gyldig IPv6-adresse.',
    'json' => 'Feltet :attribute skal være en gyldig JSON-streng.',
    'list' => 'Feltet :attribute skal være en liste.',
    'lowercase' => 'Feltet :attribute skal være med små bogstaver.',
    'lt' => [
        'array' => 'Feltet :attribute skal have færre end :value elementer.',
        'file' => 'Feltet :attribute skal være mindre end :value kilobytes.',
        'numeric' => 'Feltet :attribute skal være mindre end :value.',
        'string' => 'Feltet :attribute skal være mindre end :value tegn.',
    ],
    'lte' => [
        'array' => 'Feltet :attribute må ikke have mere end :value elementer.',
        'file' => 'Feltet :attribute skal være mindre end eller lig med :value kilobytes.',
        'numeric' => 'Feltet :attribute skal være mindre end eller lig med :value.',
        'string' => 'Feltet :attribute skal være mindre end eller lig med :value tegn.',
    ],
    'mac_address' => 'Feltet :attribute skal være en gyldig MAC-adresse.',
    'max' => [
        'array' => 'Feltet :attribute må ikke have mere end :max elementer.',
        'file' => 'Feltet :attribute må ikke være større end :max kilobytes.',
        'numeric' => 'Feltet :attribute må ikke være større end :max.',
        'string' => 'Feltet :attribute må ikke være større end :max tegn.',
    ],
    'max_digits' => 'Feltet :attribute må ikke have mere end :max cifre.',
    'mimes' => 'Feltet :attribute skal være en fil af typen: :values.',
    'mimetypes' => 'Feltet :attribute skal være en fil af typen: :values.',
    'min' => [
        'array' => 'Feltet :attribute skal have mindst :min elementer.',
        'file' => 'Feltet :attribute skal være mindst :min kilobytes.',
        'numeric' => 'Feltet :attribute skal være mindst :min.',
        'string' => 'Feltet :attribute skal være mindst :min tegn.',
    ],
    'min_digits' => 'Feltet :attribute skal have mindst :min cifre.',
    'missing' => 'Feltet :attribute skal mangle.',
    'missing_if' => 'Feltet :attribute skal mangle, når :other er :value.',
    'missing_unless' => 'Feltet :attribute skal mangle, medmindre :other er :value.',
    'missing_with' => 'Feltet :attribute skal mangle, når :values er til stede.',
    'missing_with_all' => 'Feltet :attribute skal mangle, når :values er til stede.',
    'multiple_of' => 'Feltet :attribute skal være et multiplum af :value.',
    'not_in' => 'Den valgte :attribute er ugyldig.',
    'not_regex' => 'Formatet for feltet :attribute er ugyldigt.',
    'numeric' => 'Feltet :attribute skal være et tal.',
    'password' => [
        'letters' => 'Feltet :attribute skal indeholde mindst ét bogstav.',
        'mixed' => 'Feltet :attribute skal indeholde mindst ét stort og ét lille bogstav.',
        'numbers' => 'Feltet :attribute skal indeholde mindst ét tal.',
        'symbols' => 'Feltet :attribute skal indeholde mindst ét symbol.',
        'uncompromised' => 'Den angivne :attribute er blevet fundet i et datalæk. Vælg venligst en anden :attribute.',
    ],
    'present' => 'Feltet :attribute skal være til stede.',
    'present_if' => 'Feltet :attribute skal være til stede, når :other er :value.',
    'present_unless' => 'Feltet :attribute skal være til stede, medmindre :other er :value.',
    'present_with' => 'Feltet :attribute skal være til stede, når :values er til stede.',
    'present_with_all' => 'Feltet :attribute skal være til stede, når :values er til stede.',
    'prohibited' => 'Feltet :attribute er ikke tilladt.',
    'prohibited_if' => 'Feltet :attribute er ikke tilladt, når :other er :value.',
    'prohibited_if_accepted' => 'Feltet :attribute er ikke tilladt, når :other er accepteret.',
    'prohibited_if_declined' => 'Feltet :attribute er ikke tilladt, når :other er afvist.',
    'prohibited_unless' => 'Feltet :attribute er ikke tilladt, medmindre :other er i :values.',
    'prohibits' => 'Feltet :attribute forhindrer, at :other er til stede.',
    'regex' => 'Formatet for feltet :attribute er ugyldigt.',
    'required' => 'Feltet :attribute er påkrævet.',
    'required_array_keys' => 'Feltet :attribute skal indeholde poster for: :values.',
    'required_if' => 'Feltet :attribute er påkrævet, når :other er :value.',
    'required_if_accepted' => 'Feltet :attribute er påkrævet, når :other er accepteret.',
    'required_if_declined' => 'Feltet :attribute er påkrævet, når :other er afvist.',
    'required_unless' => 'Feltet :attribute er påkrævet, medmindre :other er i :values.',
    'required_with' => 'Feltet :attribute er påkrævet, når :values er til stede.',
    'required_with_all' => 'Feltet :attribute er påkrævet, når :values er til stede.',
    'required_without' => 'Feltet :attribute er påkrævet, når :values ikke er til stede.',
    'required_without_all' => 'Feltet :attribute er påkrævet, når ingen af :values er til stede.',
    'same' => 'Feltet :attribute skal matche :other.',
    'size' => [
        'array' => 'Feltet :attribute skal indeholde :size elementer.',
        'file' => 'Feltet :attribute skal være :size kilobytes.',
        'numeric' => 'Feltet :attribute skal være :size.',
        'string' => 'Feltet :attribute skal være :size tegn.',
    ],
    'starts_with' => 'Feltet :attribute skal starte med en af følgende: :values.',
    'string' => 'Feltet :attribute skal være en streng.',
    'timezone' => 'Feltet :attribute skal være en gyldig tidszone.',
    'unique' => ':attribute er allerede taget.',
    'uploaded' => ':attribute kunne ikke uploades.',
    'uppercase' => 'Feltet :attribute skal være med store bogstaver.',
    'url' => 'Feltet :attribute skal være en gyldig URL.',
    'ulid' => 'Feltet :attribute skal være et gyldigt ULID.',
    'uuid' => 'Feltet :attribute skal være et gyldigt UUID.',

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
