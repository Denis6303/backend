<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiCodes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Validation helper: retourne les données validées ou renvoie une réponse standardisée.
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $rules
     * @return array<string,mixed>
     */
    protected function validateOrFail(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                    ->withHttpCode(\Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage(__('validation.failed'))
                    ->withData(['errors' => $validator->errors()->toArray()])
                    ->build()
            );
        }

        return $validator->validated();
    }

    /**
     * Validation standard pagination: page/per_page.
     *
     * @param  array<string,mixed>  $data
     * @return array{page?:int,per_page?:int}
     */
    protected function validateWithPagination(array $data): array
    {
        $validated = $this->validateOrFail($data, [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            'page' => Arr::get($validated, 'page'),
            'per_page' => Arr::get($validated, 'per_page'),
        ];
    }

    /**
     * Réponse paginée standard.
     */
    protected function buildPaginatedResponse($query, array $validated, string $message, $transformer = null, array $extraData = [])
    {
        $perPage = (int) ($validated['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection();
        if ($transformer) {
            $items = $items->map($transformer);
        }

        $payload = array_merge([
            'current_page' => $paginator->currentPage(),
            'data' => $items->values()->all(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ], $extraData);

        return ResponseBuilder::asSuccess()
            ->withMessage(__($message))
            ->withData($payload)
            ->build();
    }
}
