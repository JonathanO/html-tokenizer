<?php

namespace Woaf\HtmlTokenizer\Tables;

use Woaf\HtmlTokenizer\ParseError;

class ParseErrors {

    private static $errors = array (
  'eof-in-tag' => 'getEofInTag',
  'control-character-reference' => 'getControlCharacterReference',
  'missing-semicolon-after-character-reference' => 'getMissingSemicolonAfterCharacterReference',
  'eof-in-script-html-comment-like-text' => 'getEofInScriptHtmlCommentLikeText',
  'abrupt-closing-of-empty-comment' => 'getAbruptClosingOfEmptyComment',
  'unexpected-question-mark-instead-of-tag-name' => 'getUnexpectedQuestionMarkInsteadOfTagName',
  'unknown-named-character-reference' => 'getUnknownNamedCharacterReference',
  'unexpected-null-character' => 'getUnexpectedNullCharacter',
  'noncharacter-character-reference' => 'getNoncharacterCharacterReference',
  'eof-in-comment' => 'getEofInComment',
  'unexpected-character-in-attribute-name' => 'getUnexpectedCharacterInAttributeName',
  'invalid-first-character-of-tag-name' => 'getInvalidFirstCharacterOfTagName',
  'incorrectly-opened-comment' => 'getIncorrectlyOpenedComment',
  'control-character-in-input-stream' => 'getControlCharacterInInputStream',
  'unexpected-solidus-in-tag' => 'getUnexpectedSolidusInTag',
  'unexpected-character-in-unquoted-attribute-value' => 'getUnexpectedCharacterInUnquotedAttributeValue',
  'noncharacter-in-input-stream' => 'getNoncharacterInInputStream',
  'null-character-reference' => 'getNullCharacterReference',
  'absence-of-digits-in-numeric-character-reference' => 'getAbsenceOfDigitsInNumericCharacterReference',
  'character-reference-outside-unicode-range' => 'getCharacterReferenceOutsideUnicodeRange',
  'surrogate-character-reference' => 'getSurrogateCharacterReference',
  'missing-whitespace-between-attributes' => 'getMissingWhitespaceBetweenAttributes',
  'eof-before-tag-name' => 'getEofBeforeTagName',
  'eof-in-cdata' => 'getEofInCdata',
  'eof-in-doctype' => 'getEofInDoctype',
  'missing-doctype-name' => 'getMissingDoctypeName',
  'missing-whitespace-before-doctype-name' => 'getMissingWhitespaceBeforeDoctypeName',
  'invalid-character-sequence-after-doctype-name' => 'getInvalidCharacterSequenceAfterDoctypeName',
  'missing-quote-before-doctype-public-identifier' => 'getMissingQuoteBeforeDoctypePublicIdentifier',
  'missing-whitespace-after-doctype-public-keyword' => 'getMissingWhitespaceAfterDoctypePublicKeyword',
  'missing-quote-before-doctype-system-identifier' => 'getMissingQuoteBeforeDoctypeSystemIdentifier',
  'missing-whitespace-after-doctype-system-keyword' => 'getMissingWhitespaceAfterDoctypeSystemKeyword',
  'duplicate-attribute' => 'getDuplicateAttribute',
  'abrupt-doctype-public-identifier' => 'getAbruptDoctypePublicIdentifier',
  'abrupt-doctype-system-identifier' => 'getAbruptDoctypeSystemIdentifier',
  'incorrectly-closed-comment' => 'getIncorrectlyClosedComment',
  'missing-doctype-public-identifier' => 'getMissingDoctypePublicIdentifier',
  'missing-doctype-system-identifier' => 'getMissingDoctypeSystemIdentifier',
);

        public static function getEofInTag($line = 0, $col = 0) {
            return new ParseError("eof-in-tag", "eof in tag", $line, $col);
        }
        public static function getControlCharacterReference($line = 0, $col = 0) {
            return new ParseError("control-character-reference", "control character reference", $line, $col);
        }
        public static function getMissingSemicolonAfterCharacterReference($line = 0, $col = 0) {
            return new ParseError("missing-semicolon-after-character-reference", "missing semicolon after character reference", $line, $col);
        }
        public static function getEofInScriptHtmlCommentLikeText($line = 0, $col = 0) {
            return new ParseError("eof-in-script-html-comment-like-text", "eof in script html comment like text", $line, $col);
        }
        public static function getAbruptClosingOfEmptyComment($line = 0, $col = 0) {
            return new ParseError("abrupt-closing-of-empty-comment", "abrupt closing of empty comment", $line, $col);
        }
        public static function getUnexpectedQuestionMarkInsteadOfTagName($line = 0, $col = 0) {
            return new ParseError("unexpected-question-mark-instead-of-tag-name", "unexpected question mark instead of tag name", $line, $col);
        }
        public static function getUnknownNamedCharacterReference($line = 0, $col = 0) {
            return new ParseError("unknown-named-character-reference", "unknown named character reference", $line, $col);
        }
        public static function getUnexpectedNullCharacter($line = 0, $col = 0) {
            return new ParseError("unexpected-null-character", "unexpected null character", $line, $col);
        }
        public static function getNoncharacterCharacterReference($line = 0, $col = 0) {
            return new ParseError("noncharacter-character-reference", "noncharacter character reference", $line, $col);
        }
        public static function getEofInComment($line = 0, $col = 0) {
            return new ParseError("eof-in-comment", "eof in comment", $line, $col);
        }
        public static function getUnexpectedCharacterInAttributeName($line = 0, $col = 0) {
            return new ParseError("unexpected-character-in-attribute-name", "unexpected character in attribute name", $line, $col);
        }
        public static function getInvalidFirstCharacterOfTagName($line = 0, $col = 0) {
            return new ParseError("invalid-first-character-of-tag-name", "invalid first character of tag name", $line, $col);
        }
        public static function getIncorrectlyOpenedComment($line = 0, $col = 0) {
            return new ParseError("incorrectly-opened-comment", "incorrectly opened comment", $line, $col);
        }
        public static function getControlCharacterInInputStream($line = 0, $col = 0) {
            return new ParseError("control-character-in-input-stream", "control character in input stream", $line, $col);
        }
        public static function getUnexpectedSolidusInTag($line = 0, $col = 0) {
            return new ParseError("unexpected-solidus-in-tag", "unexpected solidus in tag", $line, $col);
        }
        public static function getUnexpectedCharacterInUnquotedAttributeValue($line = 0, $col = 0) {
            return new ParseError("unexpected-character-in-unquoted-attribute-value", "unexpected character in unquoted attribute value", $line, $col);
        }
        public static function getNoncharacterInInputStream($line = 0, $col = 0) {
            return new ParseError("noncharacter-in-input-stream", "noncharacter in input stream", $line, $col);
        }
        public static function getNullCharacterReference($line = 0, $col = 0) {
            return new ParseError("null-character-reference", "null character reference", $line, $col);
        }
        public static function getAbsenceOfDigitsInNumericCharacterReference($line = 0, $col = 0) {
            return new ParseError("absence-of-digits-in-numeric-character-reference", "absence of digits in numeric character reference", $line, $col);
        }
        public static function getCharacterReferenceOutsideUnicodeRange($line = 0, $col = 0) {
            return new ParseError("character-reference-outside-unicode-range", "character reference outside unicode range", $line, $col);
        }
        public static function getSurrogateCharacterReference($line = 0, $col = 0) {
            return new ParseError("surrogate-character-reference", "surrogate character reference", $line, $col);
        }
        public static function getMissingWhitespaceBetweenAttributes($line = 0, $col = 0) {
            return new ParseError("missing-whitespace-between-attributes", "missing whitespace between attributes", $line, $col);
        }
        public static function getEofBeforeTagName($line = 0, $col = 0) {
            return new ParseError("eof-before-tag-name", "eof before tag name", $line, $col);
        }
        public static function getEofInCdata($line = 0, $col = 0) {
            return new ParseError("eof-in-cdata", "eof in cdata", $line, $col);
        }
        public static function getEofInDoctype($line = 0, $col = 0) {
            return new ParseError("eof-in-doctype", "eof in doctype", $line, $col);
        }
        public static function getMissingDoctypeName($line = 0, $col = 0) {
            return new ParseError("missing-doctype-name", "missing doctype name", $line, $col);
        }
        public static function getMissingWhitespaceBeforeDoctypeName($line = 0, $col = 0) {
            return new ParseError("missing-whitespace-before-doctype-name", "missing whitespace before doctype name", $line, $col);
        }
        public static function getInvalidCharacterSequenceAfterDoctypeName($line = 0, $col = 0) {
            return new ParseError("invalid-character-sequence-after-doctype-name", "invalid character sequence after doctype name", $line, $col);
        }
        public static function getMissingQuoteBeforeDoctypePublicIdentifier($line = 0, $col = 0) {
            return new ParseError("missing-quote-before-doctype-public-identifier", "missing quote before doctype public identifier", $line, $col);
        }
        public static function getMissingWhitespaceAfterDoctypePublicKeyword($line = 0, $col = 0) {
            return new ParseError("missing-whitespace-after-doctype-public-keyword", "missing whitespace after doctype public keyword", $line, $col);
        }
        public static function getMissingQuoteBeforeDoctypeSystemIdentifier($line = 0, $col = 0) {
            return new ParseError("missing-quote-before-doctype-system-identifier", "missing quote before doctype system identifier", $line, $col);
        }
        public static function getMissingWhitespaceAfterDoctypeSystemKeyword($line = 0, $col = 0) {
            return new ParseError("missing-whitespace-after-doctype-system-keyword", "missing whitespace after doctype system keyword", $line, $col);
        }
        public static function getDuplicateAttribute($line = 0, $col = 0) {
            return new ParseError("duplicate-attribute", "duplicate attribute", $line, $col);
        }
        public static function getAbruptDoctypePublicIdentifier($line = 0, $col = 0) {
            return new ParseError("abrupt-doctype-public-identifier", "abrupt doctype public identifier", $line, $col);
        }
        public static function getAbruptDoctypeSystemIdentifier($line = 0, $col = 0) {
            return new ParseError("abrupt-doctype-system-identifier", "abrupt doctype system identifier", $line, $col);
        }
        public static function getIncorrectlyClosedComment($line = 0, $col = 0) {
            return new ParseError("incorrectly-closed-comment", "incorrectly closed comment", $line, $col);
        }
        public static function getMissingDoctypePublicIdentifier($line = 0, $col = 0) {
            return new ParseError("missing-doctype-public-identifier", "missing doctype public identifier", $line, $col);
        }
        public static function getMissingDoctypeSystemIdentifier($line = 0, $col = 0) {
            return new ParseError("missing-doctype-system-identifier", "missing doctype system identifier", $line, $col);
        }

    /**
     * @return ParseError
     */
    public static function forCode($code, $col, $line) {
        $method = self::$errors[$code];
        return self::$method($line, $col);
    }

}