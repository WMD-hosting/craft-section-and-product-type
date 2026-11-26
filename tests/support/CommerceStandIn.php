<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\support;

use Craft;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\ProductTypes;
use ReflectionClass;

/**
 * Makes Commerce count as installed and enabled, without a Craft application or a database.
 *
 * The Product Type field asks `Commerce::getInstance()`, which looks the plugin up among the
 * application's loaded modules. This puts a Commerce plugin there whose product type service
 * answers from memory. Call off() when the test is done: every other test relies on there being
 * no application.
 */
final class CommerceStandIn
{
    public static function available(): bool
    {
        return class_exists(Commerce::class);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string}> $productTypes `id => [uid, name, handle]`
     */
    public static function on(array $productTypes): void
    {
        $models = [];
        foreach ($productTypes as $id => [$uid, $name, $handle]) {
            $model = (new ReflectionClass(ProductType::class))->newInstanceWithoutConstructor();
            $model->id = $id;
            $model->uid = $uid;
            $model->name = $name;
            $model->handle = $handle;
            $models[$id] = $model;
        }

        $service = new class($models) extends ProductTypes {
            /**
             * @param array<int, ProductType> $models
             */
            public function __construct(private array $models)
            {
            }

            public function getAllProductTypes(): array
            {
                return array_values($this->models);
            }

            public function getProductTypeById(int $productTypeId): ?ProductType
            {
                return $this->models[$productTypeId] ?? null;
            }
        };

        $commerce = (new ReflectionClass(Commerce::class))->newInstanceWithoutConstructor();
        $commerce->set('productTypes', $service);

        // Just enough of an application for `Commerce::getInstance()` and `Craft::t()`.
        Craft::$app = new class($commerce) {
            /** @var array<class-string, object> */
            public array $loadedModules;

            public string $language = 'en-US';

            public function __construct(object $commerce)
            {
                $this->loadedModules = [Commerce::class => $commerce];
            }

            public function getI18n(): object
            {
                return new class() {
                    /**
                     * @param array<string, mixed> $params
                     */
                    public function translate(string $category, string $message, array $params, string $language): string
                    {
                        return strtr($message, array_combine(
                            array_map(static fn(string $name) => '{' . $name . '}', array_keys($params)),
                            array_map('strval', array_values($params)),
                        ));
                    }
                };
            }
        };
    }

    public static function off(): void
    {
        Craft::$app = null;
    }
}
