<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Logo or main image of Meu Site. Only JPG, PNG or WebP up to 2 MB; SVG is refused because it can carry scripts. The
 * file is decoded and re-encoded before it is stored (WebsiteMedia).
 */
class StoreWebsiteImageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Escolha uma imagem.',
            'image.image' => 'Envie uma imagem JPG, PNG ou WebP.',
            'image.mimes' => 'Envie uma imagem JPG, PNG ou WebP.',
            'image.max' => 'A imagem pode ter no máximo 2 MB.',
            'image.dimensions' => 'A imagem deve ter entre 64 e 8000 pixels de largura e de altura.',
        ];
    }
}
