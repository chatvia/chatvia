<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Illuminate\Support\Str;

/**
 * Whether a visitor's spoken answer is a yes, in any language the dashboard
 * speaks. A voice call has nothing to click, so this is the one check between
 * "the model asked" and "the action runs": the server reads the transcript
 * itself instead of trusting that the model heard a yes. A word of refusal
 * anywhere in the answer makes it a no ("yes, but don't send it"), and an
 * answer in no known form is a no too; the model then asks again.
 */
final class SpokenYes
{
    /** Words that say yes, one per token; CJK forms are matched as substrings. */
    private const array YES = [
        // en
        'yes', 'yeah', 'yep', 'yup', 'sure', 'ok', 'okay', 'correct', 'confirm', 'confirmed', 'absolutely', 'affirmative', 'proceed', 'go',
        // ar
        'نعم', 'أجل', 'اجل', 'أيوه', 'ايوه', 'أيوا', 'ايوا', 'تمام', 'موافق', 'أكيد', 'اكيد', 'طبعا', 'طبعاً',
        // da, no, sv
        'ja', 'jo', 'gerne', 'selvfølgelig', 'bekræft', 'greit', 'selvsagt', 'bekreft', 'okej', 'visst', 'gärna', 'självklart', 'bekräfta',
        // de
        'jawohl', 'klar', 'genau', 'sicher', 'bestätigt', 'bestätige', 'einverstanden',
        // es, pt
        'sí', 'si', 'claro', 'vale', 'confirmo', 'adelante', 'correcto', 'sim', 'pode', 'certo', 'isso',
        // fa
        'بله', 'آره', 'اره', 'باشه', 'حتما', 'حتماً', 'تایید', 'اوکی',
        // fi
        'kyllä', 'joo', 'juu', 'okei', 'selvä', 'toki', 'vahvistan',
        // fr
        'oui', 'ouais', 'daccord', 'confirme', 'parfait', 'volontiers',
        // hi
        'हाँ', 'हां', 'जी', 'ठीक', 'ओके', 'बिल्कुल', 'करो',
        // id
        'ya', 'iya', 'oke', 'baik', 'silakan', 'setuju', 'benar', 'boleh',
        // pl
        'tak', 'jasne', 'dobrze', 'oczywiście', 'potwierdzam', 'zgoda',
        // ru
        'да', 'ага', 'ок', 'окей', 'хорошо', 'конечно', 'давай', 'давайте', 'подтверждаю', 'верно',
        // tr
        'evet', 'tamam', 'olur', 'peki', 'tabii', 'elbette', 'onaylıyorum', 'onayla',
        // vi
        'vâng', 'dạ', 'có', 'được', 'ừ', 'đồng',
    ];

    /** @var list<string> */
    private const array YES_CJK = [
        'はい', 'ええ', 'うん', 'いいよ', 'いいです', 'お願い', '大丈夫', 'オッケー',
        '네', '예', '응', '좋아', '그래', '맞아', '해주세요', '해 주세요', '확인',
        '是', '好', '可以', '对', '行', '确认', '確認', '确定', '沒問題', '没问题', '嗯',
    ];

    /** Words that take a yes back, one per token; CJK forms are matched as substrings. */
    private const array NO = [
        // en
        'no', 'nope', 'not', 'dont', 'never', 'cancel', 'stop', 'wait', 'nah',
        // ar
        'لا', 'كلا', 'ليس', 'لن', 'مش',
        // da, no, sv
        'nej', 'ikke', 'nei', 'inte',
        // de
        'nein', 'nicht', 'nee', 'kein', 'keine',
        // es, pt
        'nunca', 'cancela', 'não', 'nao',
        // fa
        'نه', 'خیر', 'نکن',
        // fi
        'ei', 'älä',
        // fr
        'non', 'pas', 'jamais', 'annule', 'annuler',
        // hi
        'नहीं', 'मत', 'ना',
        // id
        'tidak', 'jangan', 'nggak', 'gak', 'bukan', 'batal',
        // pl
        'nie',
        // ru
        'нет', 'не', 'отмена',
        // tr
        'hayır', 'hayir', 'yapma', 'iptal', 'değil',
        // vi
        'không', 'đừng', 'thôi',
    ];

    /** @var list<string> */
    private const array NO_CJK = [
        'いいえ', 'いや', 'やめて', 'だめ', 'ダメ', 'しないで',
        '아니', '하지마', '하지 마',
        '不', '别', '別', '没', '沒', '取消',
    ];

    public static function in(?string $words): bool
    {
        $text = Str::of((string) $words)
            ->lower()
            ->replace(["'", '’'], '')
            ->replaceMatches('/[\p{P}\p{S}]+/u', ' ')
            ->squish();

        if ($text->isEmpty()) {
            return false;
        }

        $tokens = $text->explode(' ');
        $contains = fn (string $form): bool => $text->contains($form);

        if ($tokens->intersect(self::NO)->isNotEmpty() || collect(self::NO_CJK)->contains($contains)) {
            return false;
        }

        return $tokens->intersect(self::YES)->isNotEmpty() || collect(self::YES_CJK)->contains($contains);
    }
}
