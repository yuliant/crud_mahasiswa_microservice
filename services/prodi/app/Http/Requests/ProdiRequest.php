<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProdiRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['nama_prodi' => ['required', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return [
            'nama_prodi.required' => 'Nama prodi wajib diisi.',
            'nama_prodi.string' => 'Nama prodi harus berupa teks.',
            'nama_prodi.max' => 'Nama prodi maksimal 100 karakter.',
        ];
    }
}
