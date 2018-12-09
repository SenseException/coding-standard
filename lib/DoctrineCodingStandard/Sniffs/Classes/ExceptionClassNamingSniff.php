<?php

declare(strict_types=1);

namespace DoctrineCodingStandard\Sniffs\Classes;

use DoctrineCodingStandard\Helpers\ClassHelper;
use DoctrineCodingStandard\Helpers\InheritanceHelper;
use DoctrineCodingStandard\Helpers\TokenHelper;
use DoctrineCodingStandard\Helpers\UseStatementHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use const T_ABSTRACT;
use const T_CLASS;
use const T_FINAL;

final class ExceptionClassNamingSniff implements Sniff
{
    private const CODE_NOT_AN_EXCEPTION_CLASS = 'NotAnExceptionClass';

    /**
     * @return int[]|string[]
     */
    public function register() : array
    {
        return [T_CLASS];
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
     * @param int $pointer
     */
    public function process(File $phpcsFile, $pointer) : void
    {
        $previousToken        = TokenHelper::findPreviousToken($phpcsFile, $pointer);
        $isAbstract           = $previousToken === T_ABSTRACT;
        $isFinal              = $previousToken === T_FINAL;
        $isExtendingException = $this->isExtendingException($phpcsFile, $pointer);
        $declarationName      = $phpcsFile->getDeclarationName($pointer);
        $hasExceptionName     = ClassHelper::hasExceptionSuffix((string) $declarationName);
        $hasValidClassName    = ($isAbstract && $hasExceptionName) ||
            (! $isAbstract && ! $hasExceptionName && $isFinal);

        // Class is a valid exception
        if ($hasValidClassName && $isExtendingException) {
            return;
        }

        // Class is not an exception
        if (! $hasExceptionName && ! $isExtendingException && ! $this->isImplementingException($phpcsFile, $pointer)) {
            return;
        }

        if (! $hasValidClassName) {
            $phpcsFile->addError(
                'Exception classes must end with an "Exception" suffix',
                $pointer,
                self::CODE_NOT_AN_EXCEPTION_CLASS
            );

            return;
        }

        $phpcsFile->addError(
            'Exception classes must be either abstract or final.',
            $pointer,
            self::CODE_NOT_AN_EXCEPTION_CLASS
        );
    }

    private function isExtendingException(File $phpcsFile, int $stackPtr) : bool
    {
        $extendsClass = $phpcsFile->findExtendedClassName($stackPtr);
        if ($extendsClass === false) {
            return false;
        }

        return ClassHelper::hasExceptionSuffix($extendsClass);
    }

    private function isImplementingException(File $phpcsFile, int $stackPtr) : bool
    {
        $implementedInterfaces = $phpcsFile->findImplementedInterfaceNames($stackPtr);
        if ($implementedInterfaces === false) {
            return false;
        }

        $isImplementingExceptions = InheritanceHelper::hasExceptionInterface($implementedInterfaces);
        if ($isImplementingExceptions) {
            return true;
        }

        $importedClassNames = UseStatementHelper::getUseStatements($phpcsFile);

        $isImplementingThrowable = UseStatementHelper::isImplementingThrowable(
            $importedClassNames,
            $implementedInterfaces
        );

        return $isImplementingThrowable;
    }
}
