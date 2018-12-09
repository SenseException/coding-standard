<?php

declare(strict_types=1);

namespace Doctrine\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\UseStatementHelper;
use Throwable;
use const T_ABSTRACT;
use const T_CLASS;
use function count;
use function in_array;
use function preg_grep;
use function preg_match;

final class ExceptionClassNamingSniff implements Sniff
{
    private const CODE_NOT_AN_EXCEPTION_CLASS = 'NotAnExceptionClass';

    /**
     * {@inheritdoc}
     */
    public function register() : array
    {
        return [T_CLASS];
    }

    /**
     * {@inheritdoc}
     */
    public function process(File $phpcsFile, $stackPtr) : void
    {
        $isAbstract              = $phpcsFile->findNext([T_ABSTRACT], 0) !== false;
        $isExtendingException    = $this->isExtendingException($phpcsFile, $stackPtr);
        $isImplementingException = $this->isImplementingException($phpcsFile, $stackPtr);
        $hasExceptionName        = $this->hasExceptionSuffix((string) $phpcsFile->getDeclarationName($stackPtr));
        $hasValidClassName       = ($isAbstract && $hasExceptionName) || (! $isAbstract && ! $hasExceptionName);
        $isValidException        = $hasValidClassName && ($isExtendingException || $isImplementingException);
        $isNoException           = ! $hasExceptionName && ! $isExtendingException && ! $isImplementingException;

        if ($isValidException || $isNoException) {
            return;
        }

        if (! $hasValidClassName) {
            $phpcsFile->addError(
                'Use "Exception" suffix for abstract exception classes',
                $stackPtr,
                self::CODE_NOT_AN_EXCEPTION_CLASS
            );

            return;
        }

        $phpcsFile->addError(
            'Class is not a valid exception',
            $stackPtr,
            self::CODE_NOT_AN_EXCEPTION_CLASS
        );
    }

    private function isExtendingException(File $phpcsFile, int $stackPtr) : bool
    {
        // TODO Handle exception classes without "Exception" suffix in class name
        $extendsClass = $phpcsFile->findExtendedClassName($stackPtr);
        if ($extendsClass === false) {
            return false;
        }

        return $this->hasExceptionSuffix($extendsClass);
    }

    private function isImplementingException(File $phpcsFile, int $stackPtr) : bool
    {
        $implementedInterfaces = $phpcsFile->findImplementedInterfaceNames($stackPtr);
        if ($implementedInterfaces === false) {
            return false;
        }

        $isImplementingExceptions = count(preg_grep('/Exception$/', $implementedInterfaces)) > 0;

        $importedClassNames = $this->parseImportedClassNames($phpcsFile);

        // TODO Should throwable be checked separately, because it can't be implemented on non-abstract exception class?
        $isImplementingThrowable = (in_array(Throwable::class, $importedClassNames, true) &&
            in_array(Throwable::class, $implementedInterfaces, true)) ||
            in_array('\\' . Throwable::class, $implementedInterfaces, true);

        return $isImplementingExceptions || $isImplementingThrowable;
    }

    private function hasExceptionSuffix(string $className) : bool
    {
        return preg_match('/Exception$/', $className) === 1;
    }

    /**
     * @todo Move this method into a Trait or Helper class
     *
     * @return string[]
     */
    private function parseImportedClassNames(File $phpcsFile) : array
    {
        $importedClasses = [];
        foreach (UseStatementHelper::getUseStatements($phpcsFile, 0) as $useStatement) {
            $importedClasses[$useStatement->getNameAsReferencedInFile()] = $useStatement->getFullyQualifiedTypeName();
        }

        return $importedClasses;
    }
}
