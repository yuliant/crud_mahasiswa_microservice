<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MahasiswaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_prodi' => ['required', 'integer', 'min:1'],
            'nama' => ['required', 'string', 'max:100'],
            'nim' => ['required', 'string', 'max:30', Rule::unique('mahasiswa', 'nim')->ignore($this->route('mahasiswa'))],
        ];
    }

    public function messages(): array
    {
        return [
            'id_prodi.required' => 'Silakan pilih prodi.',
            'id_prodi.integer' => 'Prodi tidak valid.',
            'id_prodi.min' => 'Prodi tidak valid.',
            'nama.required' => 'Nama mahasiswa wajib diisi.',
            'nama.string' => 'Nama mahasiswa harus berupa teks.',
            'nama.max' => 'Nama mahasiswa maksimal 100 karakter.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.string' => 'NIM harus berupa teks.',
            'nim.max' => 'NIM maksimal 30 karakter.',
            'nim.unique' => 'NIM sudah digunakan mahasiswa lain.',
        ];
    }
}
