<?php

namespace Phpactor\Extension\Laravel;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Phpactor\Completion\Bridge\ObjectRenderer\ItemDocumentation;
use Phpactor\Completion\Bridge\TolerantParser\Qualifier\ClassMemberQualifier;
use Phpactor\Completion\Bridge\TolerantParser\TolerantCompletor;
use Phpactor\Completion\Bridge\TolerantParser\TolerantQualifiable;
use Phpactor\Completion\Bridge\TolerantParser\TolerantQualifier;
use Phpactor\Completion\Core\Formatter\ObjectFormatter;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\ObjectRenderer\Model\ObjectRenderer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use Phpactor\WorseReflection\Core\TypeFactory;
use Phpactor\WorseReflection\Reflector;

class LaravelCompletor implements TolerantCompletor, TolerantQualifiable
{
    private const ELOQUENT_MODEL = '\\Illuminate\\Database\\Eloquent\\Model';
    private const ELOQUENT_BUILDER = '\\Illuminate\\Database\\Eloquent\\Builder';

    public function __construct(
        private Reflector $reflector,
        private ObjectFormatter $formatter,
        private ObjectFormatter $snippetFormatter,
        private ObjectRenderer $objectRenderer
    ) {
    }

    public function qualifier(): TolerantQualifier
    {
        return new ClassMemberQualifier();
    }

    public function complete(Node $node, TextDocument $source, ByteOffset $offset): Generator
    {
        if (!$node instanceof ScopedPropertyAccessExpression) {
            return true;
        }

        $memberStartOffset = $node->doubleColon->getFullStartPosition();

        $reflectionOffset = $this->reflector->reflectOffset($source, $memberStartOffset);
        $nodeContext = $reflectionOffset->nodeContext();
        $type = $nodeContext->type();
        if ($type->instanceof(TypeFactory::reflectedClass($this->reflector, self::ELOQUENT_MODEL))->isTrue()) {
            $builder = $this->reflector->reflectClass(self::ELOQUENT_BUILDER);
            foreach ($builder->type()->members()->methods() as $method) {
                yield Suggestion::createWithOptions($method->name(), [
                    'type' => Suggestion::TYPE_METHOD,
                    'short_description' => fn () => $this->formatter->format($method),
                    'documentation' => function () use ($method) {
                        return $this->objectRenderer->render(new ItemDocumentation(sprintf(
                            '%s::%s',
                            $method->class()->name(),
                            $method->name()
                        ), $method->docblock()->formatted(), $method));
                    },
                    'snippet' => $this->snippetFormatter->format($method),
                ]);
            }
        }

        return true;
    }
}
