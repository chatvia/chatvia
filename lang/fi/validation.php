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

    'accepted' => 'Kenttä :attribute on hyväksyttävä.',
    'accepted_if' => 'Kenttä :attribute on hyväksyttävä, kun :other on :value.',
    'active_url' => 'Kenttä :attribute ei ole kelvollinen URL-osoite.',
    'after' => 'Kentän :attribute on oltava päivämäärä, joka on :date jälkeen.',
    'after_or_equal' => 'Kentän :attribute on oltava päivämäärä, joka on :date tai sen jälkeen.',
    'alpha' => 'Kenttä :attribute saa sisältää vain kirjaimia.',
    'alpha_dash' => 'Kenttä :attribute saa sisältää vain kirjaimia, numeroita, väliviivoja ja alaviivoja.',
    'alpha_num' => 'Kenttä :attribute saa sisältää vain kirjaimia ja numeroita.',
    'any_of' => 'Kenttä :attribute on virheellinen.',
    'array' => 'Kentän :attribute on oltava taulukko.',
    'ascii' => 'Kenttä :attribute saa sisältää vain yksitavuisia aakkosnumeerisia merkkejä ja symboleita.',
    'before' => 'Kentän :attribute on oltava päivämäärä, joka on ennen :date.',
    'before_or_equal' => 'Kentän :attribute on oltava päivämäärä, joka on :date tai sitä ennen.',
    'between' => [
        'array' => 'Kentässä :attribute on oltava :min–:max kohdetta.',
        'file' => 'Kentän :attribute on oltava :min–:max kilotavua.',
        'numeric' => 'Kentän :attribute on oltava välillä :min–:max.',
        'string' => 'Kentässä :attribute on oltava :min–:max merkkiä.',
    ],
    'boolean' => 'Kentän :attribute arvon on oltava tosi tai epätosi.',
    'can' => 'Kenttä :attribute sisältää luvattoman arvon.',
    'confirmed' => 'Kentän :attribute vahvistus ei täsmää.',
    'contains' => 'Kentästä :attribute puuttuu vaadittu arvo.',
    'current_password' => 'Salasana on virheellinen.',
    'date' => 'Kenttä :attribute ei ole kelvollinen päivämäärä.',
    'date_equals' => 'Kentän :attribute on oltava päivämäärä :date.',
    'date_format' => 'Kenttä :attribute ei vastaa muotoa :format.',
    'decimal' => 'Kentässä :attribute on oltava :decimal desimaalia.',
    'declined' => 'Kenttä :attribute on hylättävä.',
    'declined_if' => 'Kenttä :attribute on hylättävä, kun :other on :value.',
    'different' => 'Kenttien :attribute ja :other on oltava erilaiset.',
    'digits' => 'Kentässä :attribute on oltava :digits numeroa.',
    'digits_between' => 'Kentässä :attribute on oltava :min–:max numeroa.',
    'dimensions' => 'Kentällä :attribute on virheelliset kuvan mitat.',
    'distinct' => 'Kentällä :attribute on arvo, joka esiintyy useammin kuin kerran.',
    'doesnt_contain' => 'Kenttä :attribute ei saa sisältää mitään seuraavista arvoista: :values.',
    'doesnt_end_with' => 'Kenttä :attribute ei saa päättyä johonkin seuraavista: :values.',
    'doesnt_start_with' => 'Kenttä :attribute ei saa alkaa jollakin seuraavista: :values.',
    'email' => 'Kentän :attribute on oltava kelvollinen sähköpostiosoite.',
    'encoding' => 'Kentän :attribute on oltava koodattu muodossa :encoding.',
    'ends_with' => 'Kentän :attribute on päätyttävä johonkin seuraavista: :values.',
    'enum' => 'Valittu arvo kentälle :attribute on virheellinen.',
    'exists' => 'Valittu arvo kentälle :attribute on virheellinen.',
    'extensions' => 'Kentän :attribute tiedostopäätteen on oltava jokin seuraavista: :values.',
    'file' => 'Kentän :attribute on oltava tiedosto.',
    'filled' => 'Kentällä :attribute on oltava arvo.',
    'gt' => [
        'array' => 'Kentässä :attribute on oltava enemmän kuin :value kohdetta.',
        'file' => 'Kentän :attribute on oltava suurempi kuin :value kilotavua.',
        'numeric' => 'Kentän :attribute on oltava suurempi kuin :value.',
        'string' => 'Kentässä :attribute on oltava enemmän kuin :value merkkiä.',
    ],
    'gte' => [
        'array' => 'Kentässä :attribute on oltava vähintään :value kohdetta.',
        'file' => 'Kentän :attribute on oltava vähintään :value kilotavua.',
        'numeric' => 'Kentän :attribute on oltava vähintään :value.',
        'string' => 'Kentässä :attribute on oltava vähintään :value merkkiä.',
    ],
    'hex_color' => 'Kentän :attribute on oltava kelvollinen heksadesimaaliväri.',
    'image' => 'Kentän :attribute on oltava kuva.',
    'in' => 'Valittu arvo kentälle :attribute on virheellinen.',
    'in_array' => 'Kentän :attribute arvon on esiinnyttävä kentässä :other.',
    'in_array_keys' => 'Kentän :attribute on sisällettävä vähintään yksi seuraavista avaimista: :values.',
    'integer' => 'Kentän :attribute on oltava kokonaisluku.',
    'ip' => 'Kentän :attribute on oltava kelvollinen IP-osoite.',
    'ipv4' => 'Kentän :attribute on oltava kelvollinen IPv4-osoite.',
    'ipv6' => 'Kentän :attribute on oltava kelvollinen IPv6-osoite.',
    'json' => 'Kentän :attribute on oltava kelvollinen JSON-merkkijono.',
    'list' => 'Kentän :attribute on oltava lista.',
    'lowercase' => 'Kenttä :attribute saa sisältää vain pieniä kirjaimia.',
    'lt' => [
        'array' => 'Kentässä :attribute on oltava vähemmän kuin :value kohdetta.',
        'file' => 'Kentän :attribute on oltava pienempi kuin :value kilotavua.',
        'numeric' => 'Kentän :attribute on oltava pienempi kuin :value.',
        'string' => 'Kentässä :attribute on oltava vähemmän kuin :value merkkiä.',
    ],
    'lte' => [
        'array' => 'Kentässä :attribute ei saa olla enemmän kuin :value kohdetta.',
        'file' => 'Kentän :attribute on oltava enintään :value kilotavua.',
        'numeric' => 'Kentän :attribute on oltava enintään :value.',
        'string' => 'Kentässä :attribute ei saa olla enemmän kuin :value merkkiä.',
    ],
    'mac_address' => 'Kentän :attribute on oltava kelvollinen MAC-osoite.',
    'max' => [
        'array' => 'Kentässä :attribute ei saa olla enemmän kuin :max kohdetta.',
        'file' => 'Kenttä :attribute ei saa olla suurempi kuin :max kilotavua.',
        'numeric' => 'Kenttä :attribute ei saa olla suurempi kuin :max.',
        'string' => 'Kentässä :attribute ei saa olla enemmän kuin :max merkkiä.',
    ],
    'max_digits' => 'Kentässä :attribute ei saa olla enemmän kuin :max numeroa.',
    'mimes' => 'Kentän :attribute on oltava tiedostotyyppiä: :values.',
    'mimetypes' => 'Kentän :attribute on oltava tiedostotyyppiä: :values.',
    'min' => [
        'array' => 'Kentässä :attribute on oltava vähintään :min kohdetta.',
        'file' => 'Kentän :attribute on oltava vähintään :min kilotavua.',
        'numeric' => 'Kentän :attribute on oltava vähintään :min.',
        'string' => 'Kentässä :attribute on oltava vähintään :min merkkiä.',
    ],
    'min_digits' => 'Kentässä :attribute on oltava vähintään :min numeroa.',
    'missing' => 'Kenttää :attribute ei saa olla olemassa.',
    'missing_if' => 'Kenttää :attribute ei saa olla olemassa, kun :other on :value.',
    'missing_unless' => 'Kenttää :attribute ei saa olla olemassa, ellei :other ole :value.',
    'missing_with' => 'Kenttää :attribute ei saa olla olemassa, kun :values on olemassa.',
    'missing_with_all' => 'Kenttää :attribute ei saa olla olemassa, kun :values ovat olemassa.',
    'multiple_of' => 'Kentän :attribute on oltava luvun :value monikerta.',
    'not_in' => 'Valittu arvo kentälle :attribute on virheellinen.',
    'not_regex' => 'Kentän :attribute muoto on virheellinen.',
    'numeric' => 'Kentän :attribute on oltava numero.',
    'password' => [
        'letters' => 'Kentän :attribute on sisällettävä vähintään yksi kirjain.',
        'mixed' => 'Kentän :attribute on sisällettävä vähintään yksi iso ja yksi pieni kirjain.',
        'numbers' => 'Kentän :attribute on sisällettävä vähintään yksi numero.',
        'symbols' => 'Kentän :attribute on sisällettävä vähintään yksi erikoismerkki.',
        'uncompromised' => 'Annettu :attribute on esiintynyt tietovuodossa. Valitse toinen :attribute.',
    ],
    'present' => 'Kentän :attribute on oltava olemassa.',
    'present_if' => 'Kentän :attribute on oltava olemassa, kun :other on :value.',
    'present_unless' => 'Kentän :attribute on oltava olemassa, ellei :other ole :value.',
    'present_with' => 'Kentän :attribute on oltava olemassa, kun :values on olemassa.',
    'present_with_all' => 'Kentän :attribute on oltava olemassa, kun :values ovat olemassa.',
    'prohibited' => 'Kenttä :attribute ei ole sallittu.',
    'prohibited_if' => 'Kenttä :attribute ei ole sallittu, kun :other on :value.',
    'prohibited_if_accepted' => 'Kenttä :attribute ei ole sallittu, kun :other on hyväksytty.',
    'prohibited_if_declined' => 'Kenttä :attribute ei ole sallittu, kun :other on hylätty.',
    'prohibited_unless' => 'Kenttä :attribute ei ole sallittu, ellei :other ole jokin seuraavista: :values.',
    'prohibits' => 'Kenttä :attribute estää kentän :other olemassaolon.',
    'regex' => 'Kentän :attribute muoto on virheellinen.',
    'required' => 'Kenttä :attribute on pakollinen.',
    'required_array_keys' => 'Kentän :attribute on sisällettävä merkinnät seuraaville avaimille: :values.',
    'required_if' => 'Kenttä :attribute on pakollinen, kun :other on :value.',
    'required_if_accepted' => 'Kenttä :attribute on pakollinen, kun :other on hyväksytty.',
    'required_if_declined' => 'Kenttä :attribute on pakollinen, kun :other on hylätty.',
    'required_unless' => 'Kenttä :attribute on pakollinen, ellei :other ole jokin seuraavista: :values.',
    'required_with' => 'Kenttä :attribute on pakollinen, kun :values on olemassa.',
    'required_with_all' => 'Kenttä :attribute on pakollinen, kun :values ovat olemassa.',
    'required_without' => 'Kenttä :attribute on pakollinen, kun :values ei ole olemassa.',
    'required_without_all' => 'Kenttä :attribute on pakollinen, kun yksikään seuraavista ei ole olemassa: :values.',
    'same' => 'Kenttien :attribute ja :other on vastattava toisiaan.',
    'size' => [
        'array' => 'Kentässä :attribute on oltava tasan :size kohdetta.',
        'file' => 'Kentän :attribute on oltava :size kilotavua.',
        'numeric' => 'Kentän :attribute on oltava :size.',
        'string' => 'Kentässä :attribute on oltava tasan :size merkkiä.',
    ],
    'starts_with' => 'Kentän :attribute on alettava jollakin seuraavista: :values.',
    'string' => 'Kentän :attribute on oltava merkkijono.',
    'timezone' => 'Kentän :attribute on oltava kelvollinen aikavyöhyke.',
    'unique' => 'Kentän :attribute arvo on jo käytössä.',
    'uploaded' => 'Kentän :attribute lataaminen epäonnistui.',
    'uppercase' => 'Kenttä :attribute saa sisältää vain suuria kirjaimia.',
    'url' => 'Kentän :attribute on oltava kelvollinen URL-osoite.',
    'ulid' => 'Kentän :attribute on oltava kelvollinen ULID.',
    'uuid' => 'Kentän :attribute on oltava kelvollinen UUID.',

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
