<?php

namespace App\Ai\Providers;

use App\Ai\Gateway\QwenGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

class QwenProvider extends Provider implements TextProvider
{
    use GeneratesText;
    use StreamsText;

    protected TextGateway $textGateway;

    public function __construct(protected array $config, protected Dispatcher $events, private readonly HttpFactory $http) {}

    /**
     * Get the provider's text gateway.
     */
    public function textGateway(): TextGateway
    {
        return $this->textGateway ??= new QwenGateway($this->http);
    }

    /**
     * Set the provider's text gateway.
     */
    public function useTextGateway(TextGateway $gateway): self
    {
        $this->textGateway = $gateway;

        return $this;
    }

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string
    {
        return $this->config['models']['text']['default'] ?? 'qwen3.7-plus';
    }

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string
    {
        return $this->config['models']['text']['cheapest'] ?? $this->defaultTextModel();
    }

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string
    {
        return $this->config['models']['text']['smartest'] ?? $this->defaultTextModel();
    }
}
