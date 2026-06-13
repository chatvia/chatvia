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

    'accepted' => 'فیلد :attribute باید پذیرفته شود.',
    'accepted_if' => 'فیلد :attribute باید پذیرفته شود وقتی :other برابر :value است.',
    'active_url' => 'فیلد :attribute باید یک آدرس اینترنتی معتبر باشد.',
    'after' => 'فیلد :attribute باید تاریخی بعد از :date باشد.',
    'after_or_equal' => 'فیلد :attribute باید تاریخی برابر یا بعد از :date باشد.',
    'alpha' => 'فیلد :attribute باید فقط شامل حروف الفبا باشد.',
    'alpha_dash' => 'فیلد :attribute باید فقط شامل حروف الفبا، اعداد، خط تیره و زیرخط باشد.',
    'alpha_num' => 'فیلد :attribute باید فقط شامل حروف الفبا و اعداد باشد.',
    'any_of' => 'فیلد :attribute نامعتبر است.',
    'array' => 'فیلد :attribute باید یک آرایه باشد.',
    'ascii' => 'فیلد :attribute باید فقط شامل کاراکترها و نمادهای تک‌بایتی ASCII باشد.',
    'before' => 'فیلد :attribute باید تاریخی قبل از :date باشد.',
    'before_or_equal' => 'فیلد :attribute باید تاریخی برابر یا قبل از :date باشد.',
    'between' => [
        'array' => 'فیلد :attribute باید بین :min و :max آیتم داشته باشد.',
        'file' => 'حجم فیلد :attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute باید بین :min و :max باشد.',
        'string' => 'طول فیلد :attribute باید بین :min و :max کاراکتر باشد.',
    ],
    'boolean' => 'فیلد :attribute باید درست یا نادرست باشد.',
    'can' => 'فیلد :attribute حاوی یک مقدار غیرمجاز است.',
    'confirmed' => 'تأییدیه فیلد :attribute مطابقت ندارد.',
    'contains' => 'فیلد :attribute فاقد یک مقدار الزامی است.',
    'current_password' => 'رمز عبور وارد شده صحیح نیست.',
    'date' => 'فیلد :attribute باید یک تاریخ معتبر باشد.',
    'date_equals' => 'فیلد :attribute باید تاریخی برابر با :date باشد.',
    'date_format' => 'فیلد :attribute باید با قالب :format مطابقت داشته باشد.',
    'decimal' => 'فیلد :attribute باید دارای :decimal رقم اعشار باشد.',
    'declined' => 'فیلد :attribute باید رد شود.',
    'declined_if' => 'فیلد :attribute باید رد شود وقتی :other برابر :value است.',
    'different' => 'فیلد :attribute باید با :other متفاوت باشد.',
    'digits' => 'فیلد :attribute باید :digits رقم باشد.',
    'digits_between' => 'فیلد :attribute باید بین :min تا :max رقم باشد.',
    'dimensions' => 'ابعاد تصویر فیلد :attribute نامعتبر است.',
    'distinct' => 'فیلد :attribute دارای مقدار تکراری است.',
    'doesnt_contain' => 'فیلد :attribute نباید هیچ‌یک از مقادیر زیر را داشته باشد: :values.',
    'doesnt_end_with' => 'فیلد :attribute نباید با یکی از مقادیر زیر خاتمه یابد: :values.',
    'doesnt_start_with' => 'فیلد :attribute نباید با یکی از مقادیر زیر شروع شود: :values.',
    'email' => 'فیلد :attribute باید یک آدرس ایمیل معتبر باشد.',
    'encoding' => 'فیلد :attribute باید با رمزگذاری :encoding باشد.',
    'ends_with' => 'فیلد :attribute باید با یکی از مقادیر زیر خاتمه یابد: :values.',
    'enum' => 'مقدار انتخاب‌شده برای فیلد :attribute نامعتبر است.',
    'exists' => 'مقدار انتخاب‌شده برای فیلد :attribute نامعتبر است.',
    'extensions' => 'فیلد :attribute باید دارای یکی از پسوندهای زیر باشد: :values.',
    'file' => 'فیلد :attribute باید یک فایل باشد.',
    'filled' => 'فیلد :attribute باید دارای مقدار باشد.',
    'gt' => [
        'array' => 'فیلد :attribute باید بیش از :value آیتم داشته باشد.',
        'file' => 'حجم فیلد :attribute باید بیشتر از :value کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute باید بیشتر از :value باشد.',
        'string' => 'طول فیلد :attribute باید بیشتر از :value کاراکتر باشد.',
    ],
    'gte' => [
        'array' => 'فیلد :attribute باید :value آیتم یا بیشتر داشته باشد.',
        'file' => 'حجم فیلد :attribute باید بزرگ‌تر یا مساوی :value کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute باید بزرگ‌تر یا مساوی :value باشد.',
        'string' => 'طول فیلد :attribute باید بزرگ‌تر یا مساوی :value کاراکتر باشد.',
    ],
    'hex_color' => 'فیلد :attribute باید یک رنگ هگزادسیمال معتبر باشد.',
    'image' => 'فیلد :attribute باید یک تصویر باشد.',
    'in' => 'مقدار انتخاب‌شده برای فیلد :attribute نامعتبر است.',
    'in_array' => 'مقدار فیلد :attribute باید در :other وجود داشته باشد.',
    'in_array_keys' => 'فیلد :attribute باید حداقل یکی از کلیدهای زیر را داشته باشد: :values.',
    'integer' => 'فیلد :attribute باید یک عدد صحیح باشد.',
    'ip' => 'فیلد :attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => 'فیلد :attribute باید یک آدرس IPv4 معتبر باشد.',
    'ipv6' => 'فیلد :attribute باید یک آدرس IPv6 معتبر باشد.',
    'json' => 'فیلد :attribute باید یک رشته JSON معتبر باشد.',
    'list' => 'فیلد :attribute باید یک فهرست باشد.',
    'lowercase' => 'فیلد :attribute باید با حروف کوچک نوشته شود.',
    'lt' => [
        'array' => 'فیلد :attribute باید کمتر از :value آیتم داشته باشد.',
        'file' => 'حجم فیلد :attribute باید کمتر از :value کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute باید کمتر از :value باشد.',
        'string' => 'طول فیلد :attribute باید کمتر از :value کاراکتر باشد.',
    ],
    'lte' => [
        'array' => 'فیلد :attribute نباید بیش از :value آیتم داشته باشد.',
        'file' => 'حجم فیلد :attribute باید کوچک‌تر یا مساوی :value کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute باید کوچک‌تر یا مساوی :value باشد.',
        'string' => 'طول فیلد :attribute باید کوچک‌تر یا مساوی :value کاراکتر باشد.',
    ],
    'mac_address' => 'فیلد :attribute باید یک آدرس MAC معتبر باشد.',
    'max' => [
        'array' => 'فیلد :attribute نباید بیشتر از :max آیتم داشته باشد.',
        'file' => 'حجم فیلد :attribute نباید بیشتر از :max کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute نباید بیشتر از :max باشد.',
        'string' => 'طول فیلد :attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'max_digits' => 'فیلد :attribute نباید بیشتر از :max رقم داشته باشد.',
    'mimes' => 'فیلد :attribute باید از نوع: :values باشد.',
    'mimetypes' => 'فیلد :attribute باید از نوع: :values باشد.',
    'min' => [
        'array' => 'فیلد :attribute باید حداقل :min آیتم داشته باشد.',
        'file' => 'حجم فیلد :attribute نباید کمتر از :min کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute نباید کمتر از :min باشد.',
        'string' => 'طول فیلد :attribute نباید کمتر از :min کاراکتر باشد.',
    ],
    'min_digits' => 'فیلد :attribute باید حداقل :min رقم داشته باشد.',
    'missing' => 'فیلد :attribute باید غایب باشد.',
    'missing_if' => 'فیلد :attribute باید غایب باشد وقتی :other برابر :value است.',
    'missing_unless' => 'فیلد :attribute باید غایب باشد مگر اینکه :other برابر :value باشد.',
    'missing_with' => 'فیلد :attribute باید غایب باشد وقتی :values وجود دارد.',
    'missing_with_all' => 'فیلد :attribute باید غایب باشد وقتی :values وجود دارند.',
    'multiple_of' => 'مقدار فیلد :attribute باید مضربی از :value باشد.',
    'not_in' => 'مقدار انتخاب‌شده برای فیلد :attribute نامعتبر است.',
    'not_regex' => 'قالب فیلد :attribute نامعتبر است.',
    'numeric' => 'فیلد :attribute باید یک عدد باشد.',
    'password' => [
        'letters' => 'فیلد :attribute باید حداقل یک حرف الفبا داشته باشد.',
        'mixed' => 'فیلد :attribute باید حداقل یک حرف بزرگ و یک حرف کوچک داشته باشد.',
        'numbers' => 'فیلد :attribute باید حداقل یک عدد داشته باشد.',
        'symbols' => 'فیلد :attribute باید حداقل یک نماد داشته باشد.',
        'uncompromised' => 'فیلد :attribute در یک نشت داده ظاهر شده است. لطفاً یک :attribute متفاوت انتخاب کنید.',
    ],
    'present' => 'فیلد :attribute باید موجود باشد.',
    'present_if' => 'فیلد :attribute باید موجود باشد وقتی :other برابر :value است.',
    'present_unless' => 'فیلد :attribute باید موجود باشد مگر اینکه :other برابر :value باشد.',
    'present_with' => 'فیلد :attribute باید موجود باشد وقتی :values وجود دارد.',
    'present_with_all' => 'فیلد :attribute باید موجود باشد وقتی :values وجود دارند.',
    'prohibited' => 'فیلد :attribute مجاز نیست.',
    'prohibited_if' => 'فیلد :attribute وقتی :other برابر :value است مجاز نیست.',
    'prohibited_if_accepted' => 'فیلد :attribute وقتی :other پذیرفته شده است مجاز نیست.',
    'prohibited_if_declined' => 'فیلد :attribute وقتی :other رد شده است مجاز نیست.',
    'prohibited_unless' => 'فیلد :attribute مجاز نیست مگر اینکه :other در :values باشد.',
    'prohibits' => 'فیلد :attribute حضور :other را منع می‌کند.',
    'regex' => 'قالب فیلد :attribute نامعتبر است.',
    'required' => 'فیلد :attribute الزامی است.',
    'required_array_keys' => 'فیلد :attribute باید شامل ورودی‌هایی برای: :values باشد.',
    'required_if' => 'فیلد :attribute وقتی :other برابر :value است الزامی است.',
    'required_if_accepted' => 'فیلد :attribute وقتی :other پذیرفته شده است الزامی است.',
    'required_if_declined' => 'فیلد :attribute وقتی :other رد شده است الزامی است.',
    'required_unless' => 'فیلد :attribute الزامی است مگر اینکه :other در :values باشد.',
    'required_with' => 'فیلد :attribute وقتی :values وجود دارد الزامی است.',
    'required_with_all' => 'فیلد :attribute وقتی :values وجود دارند الزامی است.',
    'required_without' => 'فیلد :attribute وقتی :values وجود ندارد الزامی است.',
    'required_without_all' => 'فیلد :attribute وقتی هیچ‌کدام از :values وجود ندارند الزامی است.',
    'same' => 'فیلد :attribute باید با :other مطابقت داشته باشد.',
    'size' => [
        'array' => 'فیلد :attribute باید دارای :size آیتم باشد.',
        'file' => 'حجم فیلد :attribute باید :size کیلوبایت باشد.',
        'numeric' => 'مقدار فیلد :attribute باید :size باشد.',
        'string' => 'طول فیلد :attribute باید :size کاراکتر باشد.',
    ],
    'starts_with' => 'فیلد :attribute باید با یکی از مقادیر زیر شروع شود: :values.',
    'string' => 'فیلد :attribute باید یک رشته متنی باشد.',
    'timezone' => 'فیلد :attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => 'این مقدار برای فیلد :attribute قبلاً استفاده شده است.',
    'uploaded' => 'بارگذاری فیلد :attribute با شکست مواجه شد.',
    'uppercase' => 'فیلد :attribute باید با حروف بزرگ نوشته شود.',
    'url' => 'فیلد :attribute باید یک آدرس اینترنتی معتبر باشد.',
    'ulid' => 'فیلد :attribute باید یک شناسه ULID معتبر باشد.',
    'uuid' => 'فیلد :attribute باید یک شناسه UUID معتبر باشد.',

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
