<?php

declare(strict_types=1);
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Nvl\Forms\Contracts\AnonymizeFormEntryContract;
use Nvl\Forms\Contracts\CreateFormContract;
use Nvl\Forms\Contracts\CreateFormEntryContract;
use Nvl\Forms\Contracts\DeleteFormContract;
use Nvl\Forms\Contracts\DeleteFormEntryContract;
use Nvl\Forms\Contracts\DuplicateFormContract;
use Nvl\Forms\Contracts\ExportFormEntriesContract;
use Nvl\Forms\Contracts\GetFormAnalyticsBundleContract;
use Nvl\Forms\Contracts\GetFormAnalyticsSummaryContract;
use Nvl\Forms\Contracts\GetFormForRenderContract;
use Nvl\Forms\Contracts\GetFormSelectOptionsContract;
use Nvl\Forms\Contracts\GetFormSuggestionsContract;
use Nvl\Forms\Contracts\GetFormValidationSchemaContract;
use Nvl\Forms\Contracts\HandlePublicFormSubmissionContract;
use Nvl\Forms\Contracts\ListFormEntriesContract;
use Nvl\Forms\Contracts\ListFormsContract;
use Nvl\Forms\Contracts\MarkFormEntryAsLegitimateContract;
use Nvl\Forms\Contracts\MarkFormEntryAsSpamContract;
use Nvl\Forms\Contracts\RedactFormEntryContract;
use Nvl\Forms\Contracts\SearchFormsContract;
use Nvl\Forms\Contracts\ShowFormContract;
use Nvl\Forms\Contracts\ShowFormEntryContract;
use Nvl\Forms\Contracts\UpdateFormContract;
use Nvl\Forms\Providers\FormsServiceProvider;
use Nvl\Forms\Services\FormEntries;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Services\FormReader;
use Nvl\Forms\Services\FormSubmissions;
use Nvl\Forms\Tests\FormsTestCase;

if (! in_array(dirname(__DIR__).'/Pest.php', get_included_files(), true)) {
    uses(FormsTestCase::class);
}
/** @return list<array{class-string, class-string}> */
function nvlConsumerBindingsForForms(): array
{
    return [[AnonymizeFormEntryContract::class, FormEntries::class], [CreateFormContract::class, FormLifecycle::class], [CreateFormEntryContract::class, FormSubmissions::class], [DeleteFormContract::class, FormLifecycle::class], [DeleteFormEntryContract::class, FormEntries::class], [DuplicateFormContract::class, FormLifecycle::class], [ExportFormEntriesContract::class, FormEntries::class], [GetFormAnalyticsBundleContract::class, FormReader::class], [GetFormAnalyticsSummaryContract::class, FormReader::class], [GetFormForRenderContract::class, FormReader::class], [GetFormSelectOptionsContract::class, FormReader::class], [GetFormSuggestionsContract::class, FormReader::class], [GetFormValidationSchemaContract::class, FormReader::class], [HandlePublicFormSubmissionContract::class, FormSubmissions::class], [ListFormEntriesContract::class, FormEntries::class], [ListFormsContract::class, FormReader::class], [MarkFormEntryAsLegitimateContract::class, FormEntries::class], [MarkFormEntryAsSpamContract::class, FormEntries::class], [RedactFormEntryContract::class, FormEntries::class], [SearchFormsContract::class, FormReader::class], [ShowFormContract::class, FormReader::class], [ShowFormEntryContract::class, FormEntries::class], [UpdateFormContract::class, FormLifecycle::class]];
}
test('published workflow contracts retain native signatures attributes and generic documentation', function (): void {
    $genericDocumentation = static function (string|false $documentation): array {
        if ($documentation === false) {
            return [];
        }
        preg_match_all('/@param\s+([^\r\n]+?)\s+(\$[A-Za-z_][A-Za-z0-9_]*)\b/', $documentation, $parameters, PREG_SET_ORDER);
        $result = [];
        foreach ($parameters as $parameter) {
            $type = preg_replace('/\s+/', '', $parameter[1]);
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@param'.$parameter[2]] = $type;
            }
        }
        if (preg_match('/@return\s+([^\r\n]+)/', $documentation, $return) === 1) {
            $type = '';
            $depth = 0;
            foreach (str_split($return[1]) as $character) {
                if (preg_match('/\s/', $character) === 1 && $depth === 0) {
                    break;
                }
                if (str_contains('<{([', $character)) {
                    $depth++;
                } elseif (str_contains('>})]', $character)) {
                    $depth--;
                }
                if (preg_match('/\s/', $character) !== 1) {
                    $type .= $character;
                }
            }
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@return'] = $type;
            }
        }

        return $result;
    };
    foreach (nvlConsumerBindingsForForms() as [$contract, $implementation]) {
        $interface = new ReflectionClass($contract);
        $concrete = new ReflectionClass($implementation);
        expect($interface->isInterface())->toBeTrue()->and($concrete->implementsInterface($contract))->toBeTrue();
        foreach ($interface->getMethods() as $method) {
            $native = $concrete->getMethod($method->getName());
            $return = (string) $method->getReturnType();
            $publishedTypes = $genericDocumentation($method->getDocComment());
            foreach ($genericDocumentation($native->getDocComment()) as $tag => $type) {
                expect($publishedTypes[$tag] ?? null)->toBe($type);
            }
            expect($native->isPublic())->toBeTrue()->and($native->isStatic())->toBeFalse()->and(count($method->getParameters()))->toBe(count($native->getParameters()));
            if ($return !== 'self') {
                expect((string) $native->getReturnType())->toBe($return);
            } else {
                $nativeReturn = (string) $native->getReturnType();
                expect(is_a(in_array($nativeReturn, ['self', 'static'], true) ? $native->getDeclaringClass()->getName() : $nativeReturn, $contract, true))->toBeTrue();
            }
            foreach ($method->getParameters() as $position => $parameter) {
                $actual = $native->getParameters()[$position];
                expect($actual->getName())->toBe($parameter->getName())->and((string) $actual->getType())->toBe((string) $parameter->getType())->and($actual->isVariadic())->toBe($parameter->isVariadic())->and($actual->isPassedByReference())->toBe($parameter->isPassedByReference())->and($actual->isDefaultValueAvailable())->toBe($parameter->isDefaultValueAvailable())->and(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $actual->getAttributes()))->toBe(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $parameter->getAttributes()));
                if ($parameter->isDefaultValueAvailable()) {
                    expect($actual->getDefaultValue())->toEqual($parameter->getDefaultValue());
                }
            }
        }
    }
});
test('native provider defaults resolve each workflow while preserving late host substitutes', function (): void {
    foreach (nvlConsumerBindingsForForms() as [$contract, $implementation]) {
        expect($this->app->bound($contract))->toBeTrue()->and($this->app->make($contract))->toBeInstanceOf($implementation);
        $host = Mockery::mock($contract);
        $this->app->instance($contract, $host);
        expect($this->app->make($contract))->toBe($host);
    }
});
test('provider registration preserves early interface bindings in a second native application', function (): void {
    $consumer = new Application($this->app->basePath());
    $consumer->instance('config', new Repository($this->app->make('config')->all()));
    $consumer->instance('env', 'testing');
    $consumer->register(FilesystemServiceProvider::class);
    $hosts = [];
    foreach (nvlConsumerBindingsForForms() as [$contract]) {
        $hosts[$contract] = Mockery::mock($contract);
        $consumer->instance($contract, $hosts[$contract]);
    }
    try {
        $consumer->register(FormsServiceProvider::class);
        foreach ($hosts as $contract => $host) {
            expect($consumer->make($contract))->toBe($host);
        }
    } finally {
        Container::setInstance($this->app);
        $consumer->flush();
    }
});
