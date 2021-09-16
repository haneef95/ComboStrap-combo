<?php

declare(strict_types=1);

namespace Antlr\Antlr4\Runtime;

/**
 * This class provides a default implementation of the {@see Vocabulary}
 * interface.
 *
 * @author Sam Harwell
 */
final class VocabularyImpl implements Vocabulary
{
    /** @var array<string|null> */
    private $literalNames;

    /** @var array<string|null> */
    private $symbolicNames;

    /** @var array<string|null> */
    private $displayNames;

    /** @var int */
    private $maxTokenType;

    /**
     * Constructs a new instance from the specified literal, symbolic
     * and display token names.
     *
     * @param array<string|null> $literalNames  The literal names assigned
     *                                          to tokens, or `null` if no
     *                                          literal names are assigned.
     * @param array<string|null> $symbolicNames The symbolic names assigned
     *                                          to tokens, or `null` if
     *                                          no symbolic names are assigned.
     * @param array<string|null> $displayNames  The display names assigned
     *                                          to tokens, or `null` to use
     *                                          the values in literalNames` and
     *                                          `symbolicNames` as the source
     *                                          of display names, as described
     *                                          in {@see VocabularyImpl::getDisplayName()}.
     */
    public function __construct(array $literalNames = [], array $symbolicNames = [], array $displayNames = [])
    {
        $this->literalNames = $literalNames;
        $this->symbolicNames = $symbolicNames;
        $this->displayNames = $displayNames;

        // See note here on -1 part: https://github.com/antlr/antlr4/pull/1146
        $this->maxTokenType = \max(
            \count($this->displayNames),
            \count($this->literalNames),
            \count($this->symbolicNames)
        ) - 1;
    }

    /**
     * Gets an empty {@see Vocabulary} instance.
     *
     * No literal or symbol names are assigned to token types, so
     * {@see Vocabulary::getDisplayName()} returns the numeric value for
     * all tokens except {@see Token::EOF}.
     */
    public static function emptyVocabulary() : self
    {
        static $empty;

        return $empty ?? ($empty = new self());
    }

    /**
     * Returns a {@see VocabularyImpl} instance from the specified set
     * of token names. This method acts as a compatibility layer for the single
     * `tokenNames` array generated by previous releases of ANTLR.
     *
     * The resulting vocabulary instance returns `null` for
     * {@see VocabularyImpl::getLiteralName()} and {@see VocabularyImpl::getSymbolicName()},
     * and the value from `tokenNames` for the display names.
     *
     * @param array<string|null> $tokenNames The token names, or `null` if
     *                                       no token names are available.
     *
     * @return Vocabulary A {@see Vocabulary} instance which uses `tokenNames`
     *                    for the display names of tokens.
     */
    public static function fromTokenNames(array $tokenNames = []) : Vocabulary
    {
        if (\count($tokenNames) === 0) {
            return self::emptyVocabulary();
        }

        $literalNames = $tokenNames; // copy array
        $symbolicNames = $tokenNames; // copy array

        foreach ($tokenNames as $i => $tokenName) {
            if ($tokenName === null) {
                continue;
            }

            if ($tokenName !== '') {
                $firstChar = $tokenName[0];

                if ($firstChar === '\'') {
                    $symbolicNames[$i] = null;

                    continue;
                }

                if (\ctype_upper($firstChar)) {
                    $literalNames[$i] = null;

                    continue;
                }
            }

            // wasn't a literal or symbolic name
            $literalNames[$i] = null;
            $symbolicNames[$i] = null;
        }

        return new VocabularyImpl($literalNames, $symbolicNames, $tokenNames);
    }

    public function getMaxTokenType() : int
    {
        return $this->maxTokenType;
    }

    public function getLiteralName(int $tokenType) : ?string
    {
        if ($tokenType >= 0 && $tokenType < \count($this->literalNames)) {
            return $this->literalNames[$tokenType];
        }

        return null;
    }

    public function getSymbolicName(int $tokenType) : ?string
    {
        if ($tokenType >= 0 && $tokenType < \count($this->symbolicNames)) {
            return $this->symbolicNames[$tokenType];
        }

        if ($tokenType === Token::EOF) {
            return 'EOF';
        }

        return null;
    }

    public function getDisplayName(int $tokenType) : string
    {
        if ($tokenType >= 0 && $tokenType < \count($this->displayNames)) {
            $displayName = $this->displayNames[$tokenType];

            if ($displayName !== null) {
                return $displayName;
            }
        }

        $literalName = $this->getLiteralName($tokenType);

        if ($literalName !== null) {
            return $literalName;
        }

        $symbolicName = $this->getSymbolicName($tokenType);

        if ($symbolicName !== null) {
            return $symbolicName;
        }

        return (string) $tokenType;
    }
}
