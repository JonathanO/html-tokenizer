<?php

namespace Woaf\HtmlTokenizer\Tables;

use Woaf\HtmlTokenizer\HtmlParseError;

class ParseErrors {

    private static $instance = null;
    
    /**
     * @return ParseErrors
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new ParseErrors();
        }
        return self::$instance;
    }
    
    private function __construct() {    
            $this->abruptClosingOfEmptyComment = function($line, $col = null) {
                return self::getAbruptClosingOfEmptyComment($line, $col);
            };    
            $this->abruptDoctypePublicIdentifier = function($line, $col = null) {
                return self::getAbruptDoctypePublicIdentifier($line, $col);
            };    
            $this->abruptDoctypeSystemIdentifier = function($line, $col = null) {
                return self::getAbruptDoctypeSystemIdentifier($line, $col);
            };    
            $this->absenceOfDigitsInNumericCharacterReference = function($line, $col = null) {
                return self::getAbsenceOfDigitsInNumericCharacterReference($line, $col);
            };    
            $this->cdataInHtmlContent = function($line, $col = null) {
                return self::getCdataInHtmlContent($line, $col);
            };    
            $this->characterReferenceOutsideUnicodeRange = function($line, $col = null) {
                return self::getCharacterReferenceOutsideUnicodeRange($line, $col);
            };    
            $this->controlCharacterInInputStream = function($line, $col = null) {
                return self::getControlCharacterInInputStream($line, $col);
            };    
            $this->controlCharacterReference = function($line, $col = null) {
                return self::getControlCharacterReference($line, $col);
            };    
            $this->endTagWithAttributes = function($line, $col = null) {
                return self::getEndTagWithAttributes($line, $col);
            };    
            $this->duplicateAttribute = function($line, $col = null) {
                return self::getDuplicateAttribute($line, $col);
            };    
            $this->endTagWithTrailingSolidus = function($line, $col = null) {
                return self::getEndTagWithTrailingSolidus($line, $col);
            };    
            $this->eofBeforeTagName = function($line, $col = null) {
                return self::getEofBeforeTagName($line, $col);
            };    
            $this->eofInCdata = function($line, $col = null) {
                return self::getEofInCdata($line, $col);
            };    
            $this->eofInComment = function($line, $col = null) {
                return self::getEofInComment($line, $col);
            };    
            $this->eofInDoctype = function($line, $col = null) {
                return self::getEofInDoctype($line, $col);
            };    
            $this->eofInScriptHtmlCommentLikeText = function($line, $col = null) {
                return self::getEofInScriptHtmlCommentLikeText($line, $col);
            };    
            $this->eofInTag = function($line, $col = null) {
                return self::getEofInTag($line, $col);
            };    
            $this->incorrectlyClosedComment = function($line, $col = null) {
                return self::getIncorrectlyClosedComment($line, $col);
            };    
            $this->incorrectlyOpenedComment = function($line, $col = null) {
                return self::getIncorrectlyOpenedComment($line, $col);
            };    
            $this->invalidCharacterSequenceAfterDoctypeName = function($line, $col = null) {
                return self::getInvalidCharacterSequenceAfterDoctypeName($line, $col);
            };    
            $this->invalidFirstCharacterOfTagName = function($line, $col = null) {
                return self::getInvalidFirstCharacterOfTagName($line, $col);
            };    
            $this->missingAttributeValue = function($line, $col = null) {
                return self::getMissingAttributeValue($line, $col);
            };    
            $this->missingDoctypeName = function($line, $col = null) {
                return self::getMissingDoctypeName($line, $col);
            };    
            $this->missingDoctypePublicIdentifier = function($line, $col = null) {
                return self::getMissingDoctypePublicIdentifier($line, $col);
            };    
            $this->missingDoctypeSystemIdentifier = function($line, $col = null) {
                return self::getMissingDoctypeSystemIdentifier($line, $col);
            };    
            $this->missingEndTagName = function($line, $col = null) {
                return self::getMissingEndTagName($line, $col);
            };    
            $this->missingQuoteBeforeDoctypePublicIdentifier = function($line, $col = null) {
                return self::getMissingQuoteBeforeDoctypePublicIdentifier($line, $col);
            };    
            $this->missingQuoteBeforeDoctypeSystemIdentifier = function($line, $col = null) {
                return self::getMissingQuoteBeforeDoctypeSystemIdentifier($line, $col);
            };    
            $this->missingSemicolonAfterCharacterReference = function($line, $col = null) {
                return self::getMissingSemicolonAfterCharacterReference($line, $col);
            };    
            $this->missingWhitespaceAfterDoctypePublicKeyword = function($line, $col = null) {
                return self::getMissingWhitespaceAfterDoctypePublicKeyword($line, $col);
            };    
            $this->missingWhitespaceAfterDoctypeSystemKeyword = function($line, $col = null) {
                return self::getMissingWhitespaceAfterDoctypeSystemKeyword($line, $col);
            };    
            $this->missingWhitespaceBeforeDoctypeName = function($line, $col = null) {
                return self::getMissingWhitespaceBeforeDoctypeName($line, $col);
            };    
            $this->missingWhitespaceBetweenAttributes = function($line, $col = null) {
                return self::getMissingWhitespaceBetweenAttributes($line, $col);
            };    
            $this->missingWhitespaceBetweenDoctypePublicAndSystemIdentifiers = function($line, $col = null) {
                return self::getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers($line, $col);
            };    
            $this->nestedComment = function($line, $col = null) {
                return self::getNestedComment($line, $col);
            };    
            $this->noncharacterCharacterReference = function($line, $col = null) {
                return self::getNoncharacterCharacterReference($line, $col);
            };    
            $this->noncharacterInInputStream = function($line, $col = null) {
                return self::getNoncharacterInInputStream($line, $col);
            };    
            $this->nonVoidHtmlElementStartTagWithTrailingSolidus = function($line, $col = null) {
                return self::getNonVoidHtmlElementStartTagWithTrailingSolidus($line, $col);
            };    
            $this->nullCharacterReference = function($line, $col = null) {
                return self::getNullCharacterReference($line, $col);
            };    
            $this->surrogateCharacterReference = function($line, $col = null) {
                return self::getSurrogateCharacterReference($line, $col);
            };    
            $this->surrogateInInputStream = function($line, $col = null) {
                return self::getSurrogateInInputStream($line, $col);
            };    
            $this->unexpectedCharacterAfterDoctypeSystemIdentifier = function($line, $col = null) {
                return self::getUnexpectedCharacterAfterDoctypeSystemIdentifier($line, $col);
            };    
            $this->unexpectedCharacterInAttributeName = function($line, $col = null) {
                return self::getUnexpectedCharacterInAttributeName($line, $col);
            };    
            $this->unexpectedCharacterInUnquotedAttributeValue = function($line, $col = null) {
                return self::getUnexpectedCharacterInUnquotedAttributeValue($line, $col);
            };    
            $this->unexpectedEqualsSignBeforeAttributeName = function($line, $col = null) {
                return self::getUnexpectedEqualsSignBeforeAttributeName($line, $col);
            };    
            $this->unexpectedNullCharacter = function($line, $col = null) {
                return self::getUnexpectedNullCharacter($line, $col);
            };    
            $this->unexpectedQuestionMarkInsteadOfTagName = function($line, $col = null) {
                return self::getUnexpectedQuestionMarkInsteadOfTagName($line, $col);
            };    
            $this->unexpectedSolidusInTag = function($line, $col = null) {
                return self::getUnexpectedSolidusInTag($line, $col);
            };    
            $this->unknownNamedCharacterReference = function($line, $col = null) {
                return self::getUnknownNamedCharacterReference($line, $col);
            };    }

    private static $errors = array (
  'abrupt-closing-of-empty-comment' => 'getAbruptClosingOfEmptyComment',
  'abrupt-doctype-public-identifier' => 'getAbruptDoctypePublicIdentifier',
  'abrupt-doctype-system-identifier' => 'getAbruptDoctypeSystemIdentifier',
  'absence-of-digits-in-numeric-character-reference' => 'getAbsenceOfDigitsInNumericCharacterReference',
  'cdata-in-html-content' => 'getCdataInHtmlContent',
  'character-reference-outside-unicode-range' => 'getCharacterReferenceOutsideUnicodeRange',
  'control-character-in-input-stream' => 'getControlCharacterInInputStream',
  'control-character-reference' => 'getControlCharacterReference',
  'end-tag-with-attributes' => 'getEndTagWithAttributes',
  'duplicate-attribute' => 'getDuplicateAttribute',
  'end-tag-with-trailing-solidus' => 'getEndTagWithTrailingSolidus',
  'eof-before-tag-name' => 'getEofBeforeTagName',
  'eof-in-cdata' => 'getEofInCdata',
  'eof-in-comment' => 'getEofInComment',
  'eof-in-doctype' => 'getEofInDoctype',
  'eof-in-script-html-comment-like-text' => 'getEofInScriptHtmlCommentLikeText',
  'eof-in-tag' => 'getEofInTag',
  'incorrectly-closed-comment' => 'getIncorrectlyClosedComment',
  'incorrectly-opened-comment' => 'getIncorrectlyOpenedComment',
  'invalid-character-sequence-after-doctype-name' => 'getInvalidCharacterSequenceAfterDoctypeName',
  'invalid-first-character-of-tag-name' => 'getInvalidFirstCharacterOfTagName',
  'missing-attribute-value' => 'getMissingAttributeValue',
  'missing-doctype-name' => 'getMissingDoctypeName',
  'missing-doctype-public-identifier' => 'getMissingDoctypePublicIdentifier',
  'missing-doctype-system-identifier' => 'getMissingDoctypeSystemIdentifier',
  'missing-end-tag-name' => 'getMissingEndTagName',
  'missing-quote-before-doctype-public-identifier' => 'getMissingQuoteBeforeDoctypePublicIdentifier',
  'missing-quote-before-doctype-system-identifier' => 'getMissingQuoteBeforeDoctypeSystemIdentifier',
  'missing-semicolon-after-character-reference' => 'getMissingSemicolonAfterCharacterReference',
  'missing-whitespace-after-doctype-public-keyword' => 'getMissingWhitespaceAfterDoctypePublicKeyword',
  'missing-whitespace-after-doctype-system-keyword' => 'getMissingWhitespaceAfterDoctypeSystemKeyword',
  'missing-whitespace-before-doctype-name' => 'getMissingWhitespaceBeforeDoctypeName',
  'missing-whitespace-between-attributes' => 'getMissingWhitespaceBetweenAttributes',
  'missing-whitespace-between-doctype-public-and-system-identifiers' => 'getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers',
  'nested-comment' => 'getNestedComment',
  'noncharacter-character-reference' => 'getNoncharacterCharacterReference',
  'noncharacter-in-input-stream' => 'getNoncharacterInInputStream',
  'non-void-html-element-start-tag-with-trailing-solidus' => 'getNonVoidHtmlElementStartTagWithTrailingSolidus',
  'null-character-reference' => 'getNullCharacterReference',
  'surrogate-character-reference' => 'getSurrogateCharacterReference',
  'surrogate-in-input-stream' => 'getSurrogateInInputStream',
  'unexpected-character-after-doctype-system-identifier' => 'getUnexpectedCharacterAfterDoctypeSystemIdentifier',
  'unexpected-character-in-attribute-name' => 'getUnexpectedCharacterInAttributeName',
  'unexpected-character-in-unquoted-attribute-value' => 'getUnexpectedCharacterInUnquotedAttributeValue',
  'unexpected-equals-sign-before-attribute-name' => 'getUnexpectedEqualsSignBeforeAttributeName',
  'unexpected-null-character' => 'getUnexpectedNullCharacter',
  'unexpected-question-mark-instead-of-tag-name' => 'getUnexpectedQuestionMarkInsteadOfTagName',
  'unexpected-solidus-in-tag' => 'getUnexpectedSolidusInTag',
  'unknown-named-character-reference' => 'getUnknownNamedCharacterReference',
);

        public static function getAbruptClosingOfEmptyComment($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("abrupt-closing-of-empty-comment", "abrupt closing of empty comment", $line, $col);
        }
        
        public $abruptClosingOfEmptyComment;
        public static function getAbruptDoctypePublicIdentifier($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("abrupt-doctype-public-identifier", "abrupt doctype public identifier", $line, $col);
        }
        
        public $abruptDoctypePublicIdentifier;
        public static function getAbruptDoctypeSystemIdentifier($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("abrupt-doctype-system-identifier", "abrupt doctype system identifier", $line, $col);
        }
        
        public $abruptDoctypeSystemIdentifier;
        public static function getAbsenceOfDigitsInNumericCharacterReference($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("absence-of-digits-in-numeric-character-reference", "absence of digits in numeric character reference", $line, $col);
        }
        
        public $absenceOfDigitsInNumericCharacterReference;
        public static function getCdataInHtmlContent($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("cdata-in-html-content", "cdata in html content", $line, $col);
        }
        
        public $cdataInHtmlContent;
        public static function getCharacterReferenceOutsideUnicodeRange($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("character-reference-outside-unicode-range", "character reference outside unicode range", $line, $col);
        }
        
        public $characterReferenceOutsideUnicodeRange;
        public static function getControlCharacterInInputStream($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("control-character-in-input-stream", "control character in input stream", $line, $col);
        }
        
        public $controlCharacterInInputStream;
        public static function getControlCharacterReference($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("control-character-reference", "control character reference", $line, $col);
        }
        
        public $controlCharacterReference;
        public static function getEndTagWithAttributes($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("end-tag-with-attributes", "end tag with attributes", $line, $col);
        }
        
        public $endTagWithAttributes;
        public static function getDuplicateAttribute($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("duplicate-attribute", "duplicate attribute", $line, $col);
        }
        
        public $duplicateAttribute;
        public static function getEndTagWithTrailingSolidus($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("end-tag-with-trailing-solidus", "end tag with trailing solidus", $line, $col);
        }
        
        public $endTagWithTrailingSolidus;
        public static function getEofBeforeTagName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("eof-before-tag-name", "eof before tag name", $line, $col);
        }
        
        public $eofBeforeTagName;
        public static function getEofInCdata($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("eof-in-cdata", "eof in cdata", $line, $col);
        }
        
        public $eofInCdata;
        public static function getEofInComment($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("eof-in-comment", "eof in comment", $line, $col);
        }
        
        public $eofInComment;
        public static function getEofInDoctype($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("eof-in-doctype", "eof in doctype", $line, $col);
        }
        
        public $eofInDoctype;
        public static function getEofInScriptHtmlCommentLikeText($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("eof-in-script-html-comment-like-text", "eof in script html comment like text", $line, $col);
        }
        
        public $eofInScriptHtmlCommentLikeText;
        public static function getEofInTag($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("eof-in-tag", "eof in tag", $line, $col);
        }
        
        public $eofInTag;
        public static function getIncorrectlyClosedComment($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("incorrectly-closed-comment", "incorrectly closed comment", $line, $col);
        }
        
        public $incorrectlyClosedComment;
        public static function getIncorrectlyOpenedComment($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("incorrectly-opened-comment", "incorrectly opened comment", $line, $col);
        }
        
        public $incorrectlyOpenedComment;
        public static function getInvalidCharacterSequenceAfterDoctypeName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("invalid-character-sequence-after-doctype-name", "invalid character sequence after doctype name", $line, $col);
        }
        
        public $invalidCharacterSequenceAfterDoctypeName;
        public static function getInvalidFirstCharacterOfTagName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("invalid-first-character-of-tag-name", "invalid first character of tag name", $line, $col);
        }
        
        public $invalidFirstCharacterOfTagName;
        public static function getMissingAttributeValue($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-attribute-value", "missing attribute value", $line, $col);
        }
        
        public $missingAttributeValue;
        public static function getMissingDoctypeName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-doctype-name", "missing doctype name", $line, $col);
        }
        
        public $missingDoctypeName;
        public static function getMissingDoctypePublicIdentifier($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-doctype-public-identifier", "missing doctype public identifier", $line, $col);
        }
        
        public $missingDoctypePublicIdentifier;
        public static function getMissingDoctypeSystemIdentifier($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-doctype-system-identifier", "missing doctype system identifier", $line, $col);
        }
        
        public $missingDoctypeSystemIdentifier;
        public static function getMissingEndTagName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-end-tag-name", "missing end tag name", $line, $col);
        }
        
        public $missingEndTagName;
        public static function getMissingQuoteBeforeDoctypePublicIdentifier($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-quote-before-doctype-public-identifier", "missing quote before doctype public identifier", $line, $col);
        }
        
        public $missingQuoteBeforeDoctypePublicIdentifier;
        public static function getMissingQuoteBeforeDoctypeSystemIdentifier($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-quote-before-doctype-system-identifier", "missing quote before doctype system identifier", $line, $col);
        }
        
        public $missingQuoteBeforeDoctypeSystemIdentifier;
        public static function getMissingSemicolonAfterCharacterReference($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-semicolon-after-character-reference", "missing semicolon after character reference", $line, $col);
        }
        
        public $missingSemicolonAfterCharacterReference;
        public static function getMissingWhitespaceAfterDoctypePublicKeyword($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-whitespace-after-doctype-public-keyword", "missing whitespace after doctype public keyword", $line, $col);
        }
        
        public $missingWhitespaceAfterDoctypePublicKeyword;
        public static function getMissingWhitespaceAfterDoctypeSystemKeyword($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-whitespace-after-doctype-system-keyword", "missing whitespace after doctype system keyword", $line, $col);
        }
        
        public $missingWhitespaceAfterDoctypeSystemKeyword;
        public static function getMissingWhitespaceBeforeDoctypeName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-whitespace-before-doctype-name", "missing whitespace before doctype name", $line, $col);
        }
        
        public $missingWhitespaceBeforeDoctypeName;
        public static function getMissingWhitespaceBetweenAttributes($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-whitespace-between-attributes", "missing whitespace between attributes", $line, $col);
        }
        
        public $missingWhitespaceBetweenAttributes;
        public static function getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("missing-whitespace-between-doctype-public-and-system-identifiers", "missing whitespace between doctype public and system identifiers", $line, $col);
        }
        
        public $missingWhitespaceBetweenDoctypePublicAndSystemIdentifiers;
        public static function getNestedComment($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("nested-comment", "nested comment", $line, $col);
        }
        
        public $nestedComment;
        public static function getNoncharacterCharacterReference($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("noncharacter-character-reference", "noncharacter character reference", $line, $col);
        }
        
        public $noncharacterCharacterReference;
        public static function getNoncharacterInInputStream($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("noncharacter-in-input-stream", "noncharacter in input stream", $line, $col);
        }
        
        public $noncharacterInInputStream;
        public static function getNonVoidHtmlElementStartTagWithTrailingSolidus($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("non-void-html-element-start-tag-with-trailing-solidus", "non void html element start tag with trailing solidus", $line, $col);
        }
        
        public $nonVoidHtmlElementStartTagWithTrailingSolidus;
        public static function getNullCharacterReference($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("null-character-reference", "null character reference", $line, $col);
        }
        
        public $nullCharacterReference;
        public static function getSurrogateCharacterReference($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("surrogate-character-reference", "surrogate character reference", $line, $col);
        }
        
        public $surrogateCharacterReference;
        public static function getSurrogateInInputStream($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("surrogate-in-input-stream", "surrogate in input stream", $line, $col);
        }
        
        public $surrogateInInputStream;
        public static function getUnexpectedCharacterAfterDoctypeSystemIdentifier($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unexpected-character-after-doctype-system-identifier", "unexpected character after doctype system identifier", $line, $col);
        }
        
        public $unexpectedCharacterAfterDoctypeSystemIdentifier;
        public static function getUnexpectedCharacterInAttributeName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unexpected-character-in-attribute-name", "unexpected character in attribute name", $line, $col);
        }
        
        public $unexpectedCharacterInAttributeName;
        public static function getUnexpectedCharacterInUnquotedAttributeValue($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unexpected-character-in-unquoted-attribute-value", "unexpected character in unquoted attribute value", $line, $col);
        }
        
        public $unexpectedCharacterInUnquotedAttributeValue;
        public static function getUnexpectedEqualsSignBeforeAttributeName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unexpected-equals-sign-before-attribute-name", "unexpected equals sign before attribute name", $line, $col);
        }
        
        public $unexpectedEqualsSignBeforeAttributeName;
        public static function getUnexpectedNullCharacter($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unexpected-null-character", "unexpected null character", $line, $col);
        }
        
        public $unexpectedNullCharacter;
        public static function getUnexpectedQuestionMarkInsteadOfTagName($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unexpected-question-mark-instead-of-tag-name", "unexpected question mark instead of tag name", $line, $col);
        }
        
        public $unexpectedQuestionMarkInsteadOfTagName;
        public static function getUnexpectedSolidusInTag($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unexpected-solidus-in-tag", "unexpected solidus in tag", $line, $col);
        }
        
        public $unexpectedSolidusInTag;
        public static function getUnknownNamedCharacterReference($line, $col = null) {
            if (is_array($line)) {
                list($line, $col) = $line;
            } elseif ($col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("unknown-named-character-reference", "unknown named character reference", $line, $col);
        }
        
        public $unknownNamedCharacterReference;

    /**
     * @return HtmlParseError
     */
    public static function forCode($code, $line, $col) {
        $method = self::$errors[$code];
        return self::$method($line, $col);
    }


}