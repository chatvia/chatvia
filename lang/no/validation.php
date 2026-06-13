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

    'accepted' => 'Feltet :attribute må aksepteres.',
    'accepted_if' => 'Feltet :attribute må aksepteres når :other er :value.',
    'active_url' => 'Feltet :attribute må være en gyldig URL.',
    'after' => 'Feltet :attribute må være en dato etter :date.',
    'after_or_equal' => 'Feltet :attribute må være en dato etter eller lik :date.',
    'alpha' => 'Feltet :attribute kan bare inneholde bokstaver.',
    'alpha_dash' => 'Feltet :attribute kan bare inneholde bokstaver, tall, bindestreker og understreker.',
    'alpha_num' => 'Feltet :attribute kan bare inneholde bokstaver og tall.',
    'any_of' => 'Feltet :attribute er ugyldig.',
    'array' => 'Feltet :attribute må være en liste.',
    'ascii' => 'Feltet :attribute kan bare inneholde enkeltbyte alfanumeriske tegn og symboler.',
    'before' => 'Feltet :attribute må være en dato før :date.',
    'before_or_equal' => 'Feltet :attribute må være en dato før eller lik :date.',
    'between' => [
        'array' => 'Feltet :attribute må ha mellom :min og :max elementer.',
        'file' => 'Feltet :attribute må være mellom :min og :max kilobyte.',
        'numeric' => 'Feltet :attribute må være mellom :min og :max.',
        'string' => 'Feltet :attribute må være mellom :min og :max tegn.',
    ],
    'boolean' => 'Feltet :attribute må være sant eller usant.',
    'can' => 'Feltet :attribute inneholder en uautorisert verdi.',
    'confirmed' => 'Bekreftelsen av feltet :attribute stemmer ikke.',
    'contains' => 'Feltet :attribute mangler en påkrevd verdi.',
    'current_password' => 'Passordet er feil.',
    'date' => 'Feltet :attribute må være en gyldig dato.',
    'date_equals' => 'Feltet :attribute må være en dato lik :date.',
    'date_format' => 'Feltet :attribute må samsvare med formatet :format.',
    'decimal' => 'Feltet :attribute må ha :decimal desimalplasser.',
    'declined' => 'Feltet :attribute må avvises.',
    'declined_if' => 'Feltet :attribute må avvises når :other er :value.',
    'different' => 'Feltet :attribute og :other må være forskjellige.',
    'digits' => 'Feltet :attribute må ha :digits sifre.',
    'digits_between' => 'Feltet :attribute må ha mellom :min og :max sifre.',
    'dimensions' => 'Feltet :attribute har ugyldige bildedimensjoner.',
    'distinct' => 'Feltet :attribute har en duplikatverdi.',
    'doesnt_contain' => 'Feltet :attribute må ikke inneholde noen av følgende: :values.',
    'doesnt_end_with' => 'Feltet :attribute må ikke slutte med en av følgende: :values.',
    'doesnt_start_with' => 'Feltet :attribute må ikke starte med en av følgende: :values.',
    'email' => 'Feltet :attribute må være en gyldig e-postadresse.',
    'encoding' => 'Feltet :attribute må være kodet i :encoding.',
    'ends_with' => 'Feltet :attribute må slutte med en av følgende: :values.',
    'enum' => 'Det valgte :attribute er ugyldig.',
    'exists' => 'Det valgte :attribute er ugyldig.',
    'extensions' => 'Feltet :attribute må ha en av følgende filendelser: :values.',
    'file' => 'Feltet :attribute må være en fil.',
    'filled' => 'Feltet :attribute må ha en verdi.',
    'gt' => [
        'array' => 'Feltet :attribute må ha mer enn :value elementer.',
        'file' => 'Feltet :attribute må være større enn :value kilobyte.',
        'numeric' => 'Feltet :attribute må være større enn :value.',
        'string' => 'Feltet :attribute må ha mer enn :value tegn.',
    ],
    'gte' => [
        'array' => 'Feltet :attribute må ha :value elementer eller flere.',
        'file' => 'Feltet :attribute må være større enn eller lik :value kilobyte.',
        'numeric' => 'Feltet :attribute må være større enn eller lik :value.',
        'string' => 'Feltet :attribute må ha :value tegn eller flere.',
    ],
    'hex_color' => 'Feltet :attribute må være en gyldig heksadesimal farge.',
    'image' => 'Feltet :attribute må være et bilde.',
    'in' => 'Det valgte :attribute er ugyldig.',
    'in_array' => 'Feltet :attribute må finnes i :other.',
    'in_array_keys' => 'Feltet :attribute må inneholde minst én av følgende nøkler: :values.',
    'integer' => 'Feltet :attribute må være et heltall.',
    'ip' => 'Feltet :attribute må være en gyldig IP-adresse.',
    'ipv4' => 'Feltet :attribute må være en gyldig IPv4-adresse.',
    'ipv6' => 'Feltet :attribute må være en gyldig IPv6-adresse.',
    'json' => 'Feltet :attribute må være en gyldig JSON-streng.',
    'list' => 'Feltet :attribute må være en liste.',
    'lowercase' => 'Feltet :attribute må være med små bokstaver.',
    'lt' => [
        'array' => 'Feltet :attribute må ha færre enn :value elementer.',
        'file' => 'Feltet :attribute må være mindre enn :value kilobyte.',
        'numeric' => 'Feltet :attribute må være mindre enn :value.',
        'string' => 'Feltet :attribute må ha færre enn :value tegn.',
    ],
    'lte' => [
        'array' => 'Feltet :attribute må ikke ha mer enn :value elementer.',
        'file' => 'Feltet :attribute må være mindre enn eller lik :value kilobyte.',
        'numeric' => 'Feltet :attribute må være mindre enn eller lik :value.',
        'string' => 'Feltet :attribute må ha :value tegn eller færre.',
    ],
    'mac_address' => 'Feltet :attribute må være en gyldig MAC-adresse.',
    'max' => [
        'array' => 'Feltet :attribute må ikke ha mer enn :max elementer.',
        'file' => 'Feltet :attribute må ikke være større enn :max kilobyte.',
        'numeric' => 'Feltet :attribute må ikke være større enn :max.',
        'string' => 'Feltet :attribute må ikke ha mer enn :max tegn.',
    ],
    'max_digits' => 'Feltet :attribute må ikke ha mer enn :max sifre.',
    'mimes' => 'Feltet :attribute må være en fil av typen: :values.',
    'mimetypes' => 'Feltet :attribute må være en fil av typen: :values.',
    'min' => [
        'array' => 'Feltet :attribute må ha minst :min elementer.',
        'file' => 'Feltet :attribute må være minst :min kilobyte.',
        'numeric' => 'Feltet :attribute må være minst :min.',
        'string' => 'Feltet :attribute må ha minst :min tegn.',
    ],
    'min_digits' => 'Feltet :attribute må ha minst :min sifre.',
    'missing' => 'Feltet :attribute må mangle.',
    'missing_if' => 'Feltet :attribute må mangle når :other er :value.',
    'missing_unless' => 'Feltet :attribute må mangle med mindre :other er :value.',
    'missing_with' => 'Feltet :attribute må mangle når :values er til stede.',
    'missing_with_all' => 'Feltet :attribute må mangle når :values er til stede.',
    'multiple_of' => 'Feltet :attribute må være et multiplum av :value.',
    'not_in' => 'Det valgte :attribute er ugyldig.',
    'not_regex' => 'Formatet på feltet :attribute er ugyldig.',
    'numeric' => 'Feltet :attribute må være et tall.',
    'password' => [
        'letters' => 'Feltet :attribute må inneholde minst én bokstav.',
        'mixed' => 'Feltet :attribute må inneholde minst én stor og én liten bokstav.',
        'numbers' => 'Feltet :attribute må inneholde minst ett tall.',
        'symbols' => 'Feltet :attribute må inneholde minst ett symbol.',
        'uncompromised' => 'Det oppgitte :attribute har dukket opp i en datalekkasje. Vennligst velg et annet :attribute.',
    ],
    'present' => 'Feltet :attribute må være til stede.',
    'present_if' => 'Feltet :attribute må være til stede når :other er :value.',
    'present_unless' => 'Feltet :attribute må være til stede med mindre :other er :value.',
    'present_with' => 'Feltet :attribute må være til stede når :values er til stede.',
    'present_with_all' => 'Feltet :attribute må være til stede når :values er til stede.',
    'prohibited' => 'Feltet :attribute er ikke tillatt.',
    'prohibited_if' => 'Feltet :attribute er ikke tillatt når :other er :value.',
    'prohibited_if_accepted' => 'Feltet :attribute er ikke tillatt når :other er akseptert.',
    'prohibited_if_declined' => 'Feltet :attribute er ikke tillatt når :other er avvist.',
    'prohibited_unless' => 'Feltet :attribute er ikke tillatt med mindre :other er i :values.',
    'prohibits' => 'Feltet :attribute forbyr at :other er til stede.',
    'regex' => 'Formatet på feltet :attribute er ugyldig.',
    'required' => 'Feltet :attribute er påkrevd.',
    'required_array_keys' => 'Feltet :attribute må inneholde oppføringer for: :values.',
    'required_if' => 'Feltet :attribute er påkrevd når :other er :value.',
    'required_if_accepted' => 'Feltet :attribute er påkrevd når :other er akseptert.',
    'required_if_declined' => 'Feltet :attribute er påkrevd når :other er avvist.',
    'required_unless' => 'Feltet :attribute er påkrevd med mindre :other er i :values.',
    'required_with' => 'Feltet :attribute er påkrevd når :values er til stede.',
    'required_with_all' => 'Feltet :attribute er påkrevd når :values er til stede.',
    'required_without' => 'Feltet :attribute er påkrevd når :values ikke er til stede.',
    'required_without_all' => 'Feltet :attribute er påkrevd når ingen av :values er til stede.',
    'same' => 'Feltet :attribute må samsvare med :other.',
    'size' => [
        'array' => 'Feltet :attribute må inneholde :size elementer.',
        'file' => 'Feltet :attribute må være :size kilobyte.',
        'numeric' => 'Feltet :attribute må være :size.',
        'string' => 'Feltet :attribute må ha :size tegn.',
    ],
    'starts_with' => 'Feltet :attribute må starte med en av følgende: :values.',
    'string' => 'Feltet :attribute må være en streng.',
    'timezone' => 'Feltet :attribute må være en gyldig tidssone.',
    'unique' => ':attribute er allerede tatt.',
    'uploaded' => ':attribute kunne ikke lastes opp.',
    'uppercase' => 'Feltet :attribute må være med store bokstaver.',
    'url' => 'Feltet :attribute må være en gyldig URL.',
    'ulid' => 'Feltet :attribute må være en gyldig ULID.',
    'uuid' => 'Feltet :attribute må være en gyldig UUID.',

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
