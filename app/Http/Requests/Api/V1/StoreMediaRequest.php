<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UploadableType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $maxKb = (int) config('media.max_upload_size_mb', 20) * 1024;

        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif',
                'max:'.$maxKb,
            ],
            'uploadable_type' => [
                'required',
                'string',
                Rule::in(UploadableType::values()),
            ],
            'uploadable_id' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = UploadableType::tryFrom((string) $this->input('uploadable_type'));
            if ($type === null) {
                return;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $type->modelClass();
            $model = $modelClass::query()->find($this->integer('uploadable_id'));

            if ($model === null) {
                $validator->errors()->add(
                    'uploadable_id',
                    'The selected uploadable does not exist.',
                );

                return;
            }

            $this->attributes->set('uploadable', $model);
            $this->attributes->set('uploadable_type_enum', $type);
        });
    }

    public function uploadable(): Model
    {
        /** @var Model $model */
        $model = $this->attributes->get('uploadable');

        return $model;
    }

    public function uploadableType(): UploadableType
    {
        /** @var UploadableType $type */
        $type = $this->attributes->get('uploadable_type_enum');

        return $type;
    }
}
