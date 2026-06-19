<?php

namespace App\Http\Requests\Operational;

trait ValidatesOperationalProofFiles
{
    protected function proofFileRules(bool $isStore): array
    {
        $arrayRule = $isStore ? 'required_without:proof_file' : 'nullable';
        $legacyRule = $isStore ? 'required_without:proof_files' : 'nullable';

        return [
            'proof_files' => [$arrayRule, 'array', 'min:1', 'max:3'],
            'proof_files.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'proof_file' => [$legacyRule, 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    protected function proofFileMessages(): array
    {
        return [
            'proof_files.required_without' => __('operational.validation.proof_file_required'),
            'proof_files.array' => __('operational.validation.proof_files_array'),
            'proof_files.min' => __('operational.validation.proof_files_min'),
            'proof_files.max' => __('operational.validation.proof_files_max'),
            'proof_files.*.file' => __('operational.validation.proof_file_file'),
            'proof_files.*.image' => __('operational.validation.proof_file_invalid'),
            'proof_files.*.mimes' => __('operational.validation.proof_file_invalid'),
            'proof_files.*.max' => __('operational.validation.proof_file_max'),
            'proof_file.required_without' => __('operational.validation.proof_file_required'),
            'proof_file.file' => __('operational.validation.proof_file_file'),
            'proof_file.image' => __('operational.validation.proof_file_invalid'),
            'proof_file.mimes' => __('operational.validation.proof_file_invalid'),
            'proof_file.max' => __('operational.validation.proof_file_max'),
        ];
    }
}
