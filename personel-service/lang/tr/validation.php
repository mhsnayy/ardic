<?php

return [
    'required' => ':attribute alanı zorunludur.',
    'string' => ':attribute alanı metin olmalıdır.',
    'email' => ':attribute alanı geçerli bir e-posta adresi olmalıdır.',
    'max' => [
        'string' => ':attribute alanı en fazla :max karakter olabilir.',
    ],
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'integer' => ':attribute alanı tam sayı olmalıdır.',
    'attributes' => [
        'first_name' => 'ad',
        'last_name' => 'soyad',
        'email' => 'e-posta',
        'password' => 'şifre',
        'department_id' => 'departman',
        'name' => 'ad',
    ],
];
