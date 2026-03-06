@php
    /** @var \Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp
@if(count($endpoint->cleanBodyParameters) && $endpoint->httpMethods[0] !== 'GET')
```json
{!! json_encode($endpoint->cleanBodyParameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
```
@else
```json
{}
```
@endif
