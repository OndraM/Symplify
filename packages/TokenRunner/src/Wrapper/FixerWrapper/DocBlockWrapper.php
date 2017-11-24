<?php declare(strict_types=1);

namespace Symplify\TokenRunner\Wrapper\FixerWrapper;

use Nette\Utils\Strings;
use PhpCsFixer\DocBlock\DocBlock;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use phpDocumentor\Reflection\DocBlock as PhpDocumentorDocBlock;
use phpDocumentor\Reflection\DocBlock\Serializer;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlockFactory;
use Symplify\TokenRunner\Guard\TokenTypeGuard;

final class DocBlockWrapper
{
    /**
     * @var Tokens|null
     */
    private $tokens;

    /**
     * @var DocBlock|null
     */
    private $docBlock;

    /**
     * @var WhitespacesFixerConfig
     */
    private $whitespacesFixerConfig;

    /**
     * @var int|null
     */
    private $docBlockPosition;

    /**
     * @var PhpDocumentorDocBlock
     */
    private $phpDocumentorDocBlock;

    private function __construct(?Tokens $tokens, ?int $docBlockPosition, ?DocBlock $docBlock, ?Token $token = null)
    {
        $this->tokens = $tokens;
        $this->docBlockPosition = $docBlockPosition;
        $this->docBlock = $docBlock;

        if ($docBlock === null && $token !== null) {
            $this->docBlock = new DocBlock($token->getContent());
        }

        $docBlockFactory = DocBlockFactory::createInstance();
        $content = $token ? $token->getContent() : $docBlock->getContent();
        $this->phpDocumentorDocBlock = $docBlockFactory->create($content);

        $this->docBlockSerializer = new Serializer(4, ' ', false);
    }

    public static function createFromTokensPositionAndDocBlock(
        Tokens $tokens,
        int $docBlockPosition,
        DocBlock $docBlock
    ): self {
        TokenTypeGuard::ensureIsTokenType($tokens[$docBlockPosition], [T_COMMENT, T_DOC_COMMENT], __METHOD__);

        return new self($tokens, $docBlockPosition, $docBlock);
    }

    public static function createFromDocBlockToken(Token $docBlockToken): self
    {
        TokenTypeGuard::ensureIsTokenType($docBlockToken, [T_COMMENT, T_DOC_COMMENT], __METHOD__);

        return new self(null, null, null, $docBlockToken);
    }

    public function isSingleLine(): bool
    {
        return count($this->docBlock->getLines()) === 1;
    }

    public function getReturnType(): ?string
    {
        $returnTags = $this->phpDocumentorDocBlock->getTagsByName('return');
        if (! $returnTags) {
            return null;
        }

        return $this->clean((string) $returnTags[0]);
    }

    public function getReturnTypeDescription(): ?string
    {
        /** @var Return_[] $returnTags */
        $returnTags = $this->phpDocumentorDocBlock->getTagsByName('return');
        if (! $returnTags) {
            return null;
        }

        return (string) $returnTags[0]->getDescription();
    }

    public function getArgumentType(string $name): ?string
    {
        /** @var Param[] $paramTags */
        $paramTags = $this->phpDocumentorDocBlock->getTagsByName('param');

        foreach ($paramTags as $paramTag) {
            if ($paramTag->getVariableName() === $name) {
                return $this->clean((string) $paramTag->getType());
            }
        }

        return null;
    }

    public function getArgumentTypeDescription(string $name): ?string
    {
        $paramTags = $this->phpDocumentorDocBlock->getTagsByName('param');

        /** @var Param $paramTag */
        foreach ($paramTags as $paramTag) {
            if ($paramTag->getVariableName() === $name) {
                return $this->clean((string) $paramTag->getDescription());
            }
        }

        return null;
    }

    public function removeReturnType(): void
    {
        $returnTags = $this->phpDocumentorDocBlock->getTagsByName('return');
        foreach ($returnTags as $returnTag) {
            $this->phpDocumentorDocBlock->removeTag($returnTag);
        }

        $this->tokens[$this->docBlockPosition] = new Token([T_DOC_COMMENT, $this->docBlock->getContent()]);
    }

    public function removeParamType(string $name): void
    {
        $paramTags = $this->phpDocumentorDocBlock->getTagsByName('param');

        /** @var Param $paramTag */
        foreach ($paramTags as $paramTag) {
            if ($paramTag->getVariableName() === $name) {
                $this->phpDocumentorDocBlock->removeTag($paramTag);
                break;
            }
        }

        $docBlock = $this->docBlockSerializer->getDocComment($this->phpDocumentorDocBlock);

        $this->tokens[$this->docBlockPosition] = new Token([T_DOC_COMMENT, $docBlock]);
    }

    public function changeToMultiLine(): void
    {
        $indent = $this->whitespacesFixerConfig->getIndent();
        $lineEnding = $this->whitespacesFixerConfig->getLineEnding();
        $newLineWithIndent = $lineEnding . $indent;

        $newDocBlock = str_replace(
            [' @', '/** ', ' */'],
            [
                $newLineWithIndent . ' * @',
                '/**',
                $newLineWithIndent . ' */',
            ],
            $this->docBlock->getContent()
        );

        $this->tokens[$this->docBlockPosition] = new Token([T_DOC_COMMENT, $newDocBlock]);
    }

    public function setWhitespacesFixerConfig(WhitespacesFixerConfig $whitespacesFixerConfig): void
    {
        $this->whitespacesFixerConfig = $whitespacesFixerConfig;
    }

    public function isArrayProperty(): bool
    {
        if (! $this->docBlock->getAnnotationsOfType('var')) {
            return false;
        }

        $varAnnotation = $this->docBlock->getAnnotationsOfType('var')[0];

        $content = trim($varAnnotation->getContent());
        $content = rtrim($content, ' */');

        [, $types] = explode('@var', $content);

        $types = explode('|', trim($types));

        foreach ($types as $type) {
            if (! self::isIterableType($type)) {
                return false;
            }
        }

        return true;
    }

    public function contains(string $content): bool
    {
        return Strings::contains($this->docBlock->getContent(), $content);
    }

    private function isIterableType(string $type): bool
    {
        if (Strings::endsWith($type, '[]')) {
            return true;
        }

        if ($type === 'array') {
            return true;
        }

        return false;
    }

//    private function resolveAnnotationContent(Annotation $annotation, string $name): string
//    {
//        $content = $annotation->getContent();
//
//        if ($content === '') {
//            return $content;
//        }
//
//        [, $content] = explode('@' . $name, $content);
//
//        $content = ltrim($content, ' *');
//        $content = trim($content);
//
//        return $content;
//    }

    private function clean(string $content): string
    {
        return ltrim(trim($content), '\\');
    }
}
