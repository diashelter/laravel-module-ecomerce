<?php

/*
| Brazilian Portuguese messages for the validation rules used by this API.
*/

return [
    'array' => 'O campo :attribute deve ser uma lista.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'current_password' => 'A senha atual está incorreta.',
    'decimal' => 'O campo :attribute deve ter :decimal casas decimais.',
    'distinct' => 'O campo :attribute possui um valor duplicado.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'enum' => 'O valor selecionado para :attribute é inválido.',
    'exists' => 'O valor selecionado para :attribute é inválido.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min item(ns).',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_with' => 'O campo :attribute é obrigatório quando :values está presente.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'O valor informado para :attribute já está em uso.',
    'url' => 'O campo :attribute deve ser uma URL válida.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'current_password' => 'senha atual',
        'price' => 'preço',
        'description' => 'descrição',
        'image_url' => 'URL da imagem',
        'status' => 'status',
        'category_ids' => 'categorias',
        'category_ids.*' => 'categoria',
        'stock_quantity' => 'estoque inicial',
        'quantity' => 'quantidade',
        'operation' => 'operação',
        'items' => 'itens',
        'items.*.product_id' => 'produto',
        'items.*.quantity' => 'quantidade',
        'category' => 'categoria',
        'sort' => 'ordenação',
    ],
];
