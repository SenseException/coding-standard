<?php

declare(strict_types=1);

namespace DoctrineCodingStandard\Sniffs\Classes;

use DoctrineCodingStandard\Helpers\UseStatementHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use const T_EXTENDS;
use const T_INTERFACE;
use const T_OPEN_CURLY_BRACKET;
use function array_combine;
use function array_map;
use function count;
use function explode;
use function preg_grep;
use function preg_match;
use function trim;

final class ExceptionInterfaceNamingSniff implements Sniff
{
    private const CODE_NOT_AN_EXCEPTION = 'NotAnException';

    /**
     * {@inheritdoc}
     */
    public function register() : array
    {
        return [T_INTERFACE];
    }

    /**
     * {@inheritdoc}
     */
    public function process(File $phpcsFile, $stackPtr) : void
    {
        $importedClassNames = UseStatementHelper::getUseStatements($phpcsFile);
        $extendedInterfaces = $this->parseExtendedInterfaces($phpcsFile, $stackPtr);

        // Set original classname instead of alias
        $extendedInterfaces = array_map(function (string $extendedInterface) use ($importedClassNames) : string {
            return $importedClassNames[$extendedInterface] ?? $extendedInterface;
        }, $extendedInterfaces);

        $hasExceptionName = preg_match('/Exception$/', (string) $phpcsFile->getDeclarationName($stackPtr)) === 1;

        $isExtendingThrowable = UseStatementHelper::isImplementingThrowable($importedClassNames, $extendedInterfaces);

        // Expects that an interface with the suffix "Exception" is a valid exception interface
        $isExtendingException = count(preg_grep('/Exception$/', $extendedInterfaces)) > 0;

        $isValidInterface = $hasExceptionName && ($isExtendingException || $isExtendingThrowable);
        $isNoException    = ! $hasExceptionName && ! $isExtendingException && ! $isExtendingThrowable;
        if ($isValidInterface || $isNoException) {
            return;
        }

        if ($hasExceptionName && ! $isExtendingException && ! $isExtendingThrowable) {
            $phpcsFile->addError(
                'Interface does not extend an exception interface',
                $stackPtr,
                self::CODE_NOT_AN_EXCEPTION
            );

            return;
        }

        $phpcsFile->addError(
            'Exception interface needs an "Exception" name suffix',
            $stackPtr,
            self::CODE_NOT_AN_EXCEPTION
        );
    }

    /**
     * @return string[]
     */
    private function parseExtendedInterfaces(File $phpcsFile, int $stackPtr) : array
    {
        $limit = $phpcsFile->findNext([T_OPEN_CURLY_BRACKET], $stackPtr) - 1;
        $start = $phpcsFile->findNext([T_EXTENDS], $stackPtr, $limit);

        if ($start === false) {
            return [];
        }

        $extendedInterfaces = explode(',', $phpcsFile->getTokensAsString($start + 1, $limit - $start));

        $extendedInterfaces = array_map('trim', $extendedInterfaces);
        $interfaceNames     = array_map(function ($interfaceName) : string {
            return trim($interfaceName, '\\');
        }, $extendedInterfaces);

        return array_combine($interfaceNames, $extendedInterfaces);
    }
}
