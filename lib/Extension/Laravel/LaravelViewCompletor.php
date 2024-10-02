<?php

namespace Phpactor\Extension\Laravel;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\ArgumentExpression;
use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Phpactor\Completion\Bridge\TolerantParser\Qualifier\AlwaysQualfifier;
use Phpactor\Completion\Bridge\TolerantParser\TolerantCompletor;
use Phpactor\Completion\Bridge\TolerantParser\TolerantQualifiable;
use Phpactor\Completion\Bridge\TolerantParser\TolerantQualifier;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\Extension\Laravel\Providers\ViewsProvider;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;

use Phpactor\WorseReflection\Core\Util\NodeUtil;
use function sprintf;

class LaravelViewCompletor implements TolerantCompletor, TolerantQualifiable
{
    public function __construct(
        private readonly ViewsProvider $viewsProvider,
    ) {
    }

    // FIX: realize why does not in both, one is a call exresion and the other is a membercallexpression

    public function complete(Node $node, TextDocument $source, ByteOffset $offset): Generator
    {
        $inQuote = false;

        $argument = $node->getFirstAncestor(ArgumentExpression::class);

        if ($node instanceof StringLiteral) {
            $inQuote = true;
            $node = $node->getFirstAncestor(CallExpression::class);
        }

        if (!$node instanceof CallExpression) {
            $node = $node->getFirstAncestor(CallExpression::class);
        }

        if (!$node instanceof CallExpression) {
            return;
        }

        $memberAccess = $node->callableExpression;
        if ($memberAccess instanceof MemberAccessExpression) {
            $methodName = NodeUtil::nameFromTokenOrNode($node, $memberAccess->memberName);
        } else {
            $methodName = $node->callableExpression->getText();
        }

        if ($methodName !== 'view') {
            return;
        }

        if (
            $argument?->getPreviousSibling() != null
            && $argument?->name?->getText((string) $source) != 'view'
        ) {
            return;
        }

        // FIX: move directly to it's own class and only needs to call in one.
        foreach ($this->viewsProvider->get() as $viewName => $file) {
            $value = $inQuote ? $viewName : sprintf("'%s'", $viewName);
            yield Suggestion::createWithOptions($value, [
                'type' => Suggestion::TYPE_VALUE,
                'short_description' => $viewName,
                'documentation' => sprintf('**Blade View**: %s', $file),
                'priority' => 555,
            ]);
        }


        return true;
    }

    public function qualifier(): TolerantQualifier
    {
        return new AlwaysQualfifier();
    }
}
