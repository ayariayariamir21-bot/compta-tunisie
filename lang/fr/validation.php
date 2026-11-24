<?php

/*
|--------------------------------------------------------------------------
| Messages de validation (français)
|--------------------------------------------------------------------------
|
| Règles courantes de l'application. Les règles absentes retombent sur
| l'anglais (fallback_locale).
|
*/

return [

    'accepted' => 'Le champ « :attribute » doit être accepté.',
    'after' => 'Le champ « :attribute » doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ « :attribute » doit être une date postérieure ou égale au :date.',
    'alpha_dash' => 'Le champ « :attribute » ne peut contenir que des lettres, chiffres, tirets et underscores.',
    'alpha_num' => 'Le champ « :attribute » ne peut contenir que des lettres et des chiffres.',
    'array' => 'Le champ « :attribute » doit être une liste.',
    'before' => 'Le champ « :attribute » doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ « :attribute » doit être une date antérieure ou égale au :date.',
    'between' => [
        'array' => 'Le champ « :attribute » doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier « :attribute » doit peser entre :min et :max Ko.',
        'numeric' => 'Le champ « :attribute » doit être compris entre :min et :max.',
        'string' => 'Le champ « :attribute » doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ « :attribute » doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ « :attribute » ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ « :attribute » doit être une date valide.',
    'date_equals' => 'Le champ « :attribute » doit être une date égale au :date.',
    'date_format' => 'Le champ « :attribute » doit respecter le format :format.',
    'decimal' => 'Le champ « :attribute » doit comporter :decimal décimales.',
    'different' => 'Les champs « :attribute » et « :other » doivent être différents.',
    'digits' => 'Le champ « :attribute » doit contenir :digits chiffres.',
    'digits_between' => 'Le champ « :attribute » doit contenir entre :min et :max chiffres.',
    'distinct' => 'Le champ « :attribute » contient une valeur en double.',
    'email' => 'Le champ « :attribute » doit être une adresse e-mail valide.',
    'enum' => 'La valeur sélectionnée pour « :attribute » est invalide.',
    'exists' => 'La valeur sélectionnée pour « :attribute » est invalide.',
    'file' => 'Le champ « :attribute » doit être un fichier.',
    'filled' => 'Le champ « :attribute » doit avoir une valeur.',
    'gt' => [
        'numeric' => 'Le champ « :attribute » doit être supérieur à :value.',
        'string' => 'Le champ « :attribute » doit contenir plus de :value caractères.',
    ],
    'gte' => [
        'numeric' => 'Le champ « :attribute » doit être supérieur ou égal à :value.',
        'string' => 'Le champ « :attribute » doit contenir au moins :value caractères.',
    ],
    'image' => 'Le champ « :attribute » doit être une image.',
    'in' => 'La valeur sélectionnée pour « :attribute » est invalide.',
    'integer' => 'Le champ « :attribute » doit être un nombre entier.',
    'lowercase' => 'Le champ « :attribute » doit être en minuscules.',
    'lt' => [
        'numeric' => 'Le champ « :attribute » doit être inférieur à :value.',
        'string' => 'Le champ « :attribute » doit contenir moins de :value caractères.',
    ],
    'lte' => [
        'numeric' => 'Le champ « :attribute » doit être inférieur ou égal à :value.',
        'string' => 'Le champ « :attribute » doit contenir au plus :value caractères.',
    ],
    'max' => [
        'array' => 'Le champ « :attribute » ne peut pas contenir plus de :max éléments.',
        'file' => 'Le fichier « :attribute » ne peut pas dépasser :max Ko.',
        'numeric' => 'Le champ « :attribute » ne peut pas être supérieur à :max.',
        'string' => 'Le champ « :attribute » ne peut pas dépasser :max caractères.',
    ],
    'mimes' => 'Le champ « :attribute » doit être un fichier de type : :values.',
    'min' => [
        'array' => 'Le champ « :attribute » doit contenir au moins :min éléments.',
        'file' => 'Le fichier « :attribute » doit peser au moins :min Ko.',
        'numeric' => 'Le champ « :attribute » doit être supérieur ou égal à :min.',
        'string' => 'Le champ « :attribute » doit contenir au moins :min caractères.',
    ],
    'not_in' => 'La valeur sélectionnée pour « :attribute » est invalide.',
    'numeric' => 'Le champ « :attribute » doit être un nombre.',
    'password' => [
        'letters' => 'Le champ « :attribute » doit contenir au moins une lettre.',
        'mixed' => 'Le champ « :attribute » doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ « :attribute » doit contenir au moins un chiffre.',
        'symbols' => 'Le champ « :attribute » doit contenir au moins un symbole.',
        'uncompromised' => 'Ce « :attribute » apparaît dans une fuite de données. Choisissez-en un autre.',
    ],
    'present' => 'Le champ « :attribute » doit être présent.',
    'regex' => 'Le format du champ « :attribute » est invalide.',
    'required' => 'Le champ « :attribute » est obligatoire.',
    'required_if' => 'Le champ « :attribute » est obligatoire quand « :other » vaut :value.',
    'required_unless' => 'Le champ « :attribute » est obligatoire sauf si « :other » vaut :values.',
    'required_with' => 'Le champ « :attribute » est obligatoire quand :values est renseigné.',
    'required_without' => 'Le champ « :attribute » est obligatoire quand :values n\'est pas renseigné.',
    'same' => 'Les champs « :attribute » et « :other » doivent être identiques.',
    'size' => [
        'array' => 'Le champ « :attribute » doit contenir :size éléments.',
        'file' => 'Le fichier « :attribute » doit peser :size Ko.',
        'numeric' => 'Le champ « :attribute » doit valoir :size.',
        'string' => 'Le champ « :attribute » doit contenir :size caractères.',
    ],
    'string' => 'Le champ « :attribute » doit être du texte.',
    'timezone' => 'Le champ « :attribute » doit être un fuseau horaire valide.',
    'unique' => 'La valeur du champ « :attribute » est déjà utilisée.',
    'uppercase' => 'Le champ « :attribute » doit être en majuscules.',
    'url' => 'Le champ « :attribute » doit être une URL valide.',

    'custom' => [],

    'attributes' => [
        'name' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'current_password' => 'mot de passe actuel',
        'code' => 'code',
        'amount' => 'montant',
        'withholding_rate' => 'taux de retenue à la source',
        'stamp_duty_amount' => 'droit de timbre',
        'payment_date' => 'date de règlement',
        'invoice_date' => 'date de facture',
        'due_date' => "date d'échéance",
        'quantity' => 'quantité',
        'unit_price' => 'prix unitaire',
    ],

];
