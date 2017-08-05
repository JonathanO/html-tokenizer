<?php

namespace Woaf\HtmlTokenizer\Tables;

use Woaf\HtmlTokenizer\HtmlParseError;

class ParseErrors {

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

        public static function getAbruptClosingOfEmptyComment($line = 0, $col = 0) {
            return new HtmlParseError("abrupt-closing-of-empty-comment", "abrupt closing of empty comment", $line, $col);
        }
        public static function getAbruptDoctypePublicIdentifier($line = 0, $col = 0) {
            return new HtmlParseError("abrupt-doctype-public-identifier", "abrupt doctype public identifier", $line, $col);
        }
        public static function getAbruptDoctypeSystemIdentifier($line = 0, $col = 0) {
            return new HtmlParseError("abrupt-doctype-system-identifier", "abrupt doctype system identifier", $line, $col);
        }
        public static function getAbsenceOfDigitsInNumericCharacterReference($line = 0, $col = 0) {
            return new HtmlParseError("absence-of-digits-in-numeric-character-reference", "absence of digits in numeric character reference", $line, $col);
        }
        public static function getCdataInHtmlContent($line = 0, $col = 0) {
            return new HtmlParseError("cdata-in-html-content", "cdata in html content", $line, $col);
        }
        public static function getCharacterReferenceOutsideUnicodeRange($line = 0, $col = 0) {
            return new HtmlParseError("character-reference-outside-unicode-range", "character reference outside unicode range", $line, $col);
        }
        public static function getControlCharacterInInputStream($line = 0, $col = 0) {
            return new HtmlParseError("control-character-in-input-stream", "control character in input stream", $line, $col);
        }
        public static function getControlCharacterReference($line = 0, $col = 0) {
            return new HtmlParseError("control-character-reference", "control character reference", $line, $col);
        }
        public static function getEndTagWithAttributes($line = 0, $col = 0) {
            return new HtmlParseError("end-tag-with-attributes", "end tag with attributes", $line, $col);
        }
        public static function getDuplicateAttribute($line = 0, $col = 0) {
            return new HtmlParseError("duplicate-attribute", "duplicate attribute", $line, $col);
        }
        public static function getEndTagWithTrailingSolidus($line = 0, $col = 0) {
            return new HtmlParseError("end-tag-with-trailing-solidus", "end tag with trailing solidus", $line, $col);
        }
        public static function getEofBeforeTagName($line = 0, $col = 0) {
            return new HtmlParseError("eof-before-tag-name", "eof before tag name", $line, $col);
        }
        public static function getEofInCdata($line = 0, $col = 0) {
            return new HtmlParseError("eof-in-cdata", "eof in cdata", $line, $col);
        }
        public static function getEofInComment($line = 0, $col = 0) {
            return new HtmlParseError("eof-in-comment", "eof in comment", $line, $col);
        }
        public static function getEofInDoctype($line = 0, $col = 0) {
            return new HtmlParseError("eof-in-doctype", "eof in doctype", $line, $col);
        }
        public static function getEofInScriptHtmlCommentLikeText($line = 0, $col = 0) {
            return new HtmlParseError("eof-in-script-html-comment-like-text", "eof in script html comment like text", $line, $col);
        }
        public static function getEofInTag($line = 0, $col = 0) {
            return new HtmlParseError("eof-in-tag", "eof in tag", $line, $col);
        }
        public static function getIncorrectlyClosedComment($line = 0, $col = 0) {
            return new HtmlParseError("incorrectly-closed-comment", "incorrectly closed comment", $line, $col);
        }
        public static function getIncorrectlyOpenedComment($line = 0, $col = 0) {
            return new HtmlParseError("incorrectly-opened-comment", "incorrectly opened comment", $line, $col);
        }
        public static function getInvalidCharacterSequenceAfterDoctypeName($line = 0, $col = 0) {
            return new HtmlParseError("invalid-character-sequence-after-doctype-name", "invalid character sequence after doctype name", $line, $col);
        }
        public static function getInvalidFirstCharacterOfTagName($line = 0, $col = 0) {
            return new HtmlParseError("invalid-first-character-of-tag-name", "invalid first character of tag name", $line, $col);
        }
        public static function getMissingAttributeValue($line = 0, $col = 0) {
            return new HtmlParseError("missing-attribute-value", "missing attribute value", $line, $col);
        }
        public static function getMissingDoctypeName($line = 0, $col = 0) {
            return new HtmlParseError("missing-doctype-name", "missing doctype name", $line, $col);
        }
        public static function getMissingDoctypePublicIdentifier($line = 0, $col = 0) {
            return new HtmlParseError("missing-doctype-public-identifier", "missing doctype public identifier", $line, $col);
        }
        public static function getMissingDoctypeSystemIdentifier($line = 0, $col = 0) {
            return new HtmlParseError("missing-doctype-system-identifier", "missing doctype system identifier", $line, $col);
        }
        public static function getMissingEndTagName($line = 0, $col = 0) {
            return new HtmlParseError("missing-end-tag-name", "missing end tag name", $line, $col);
        }
        public static function getMissingQuoteBeforeDoctypePublicIdentifier($line = 0, $col = 0) {
            return new HtmlParseError("missing-quote-before-doctype-public-identifier", "missing quote before doctype public identifier", $line, $col);
        }
        public static function getMissingQuoteBeforeDoctypeSystemIdentifier($line = 0, $col = 0) {
            return new HtmlParseError("missing-quote-before-doctype-system-identifier", "missing quote before doctype system identifier", $line, $col);
        }
        public static function getMissingSemicolonAfterCharacterReference($line = 0, $col = 0) {
            return new HtmlParseError("missing-semicolon-after-character-reference", "missing semicolon after character reference", $line, $col);
        }
        public static function getMissingWhitespaceAfterDoctypePublicKeyword($line = 0, $col = 0) {
            return new HtmlParseError("missing-whitespace-after-doctype-public-keyword", "missing whitespace after doctype public keyword", $line, $col);
        }
        public static function getMissingWhitespaceAfterDoctypeSystemKeyword($line = 0, $col = 0) {
            return new HtmlParseError("missing-whitespace-after-doctype-system-keyword", "missing whitespace after doctype system keyword", $line, $col);
        }
        public static function getMissingWhitespaceBeforeDoctypeName($line = 0, $col = 0) {
            return new HtmlParseError("missing-whitespace-before-doctype-name", "missing whitespace before doctype name", $line, $col);
        }
        public static function getMissingWhitespaceBetweenAttributes($line = 0, $col = 0) {
            return new HtmlParseError("missing-whitespace-between-attributes", "missing whitespace between attributes", $line, $col);
        }
        public static function getMissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers($line = 0, $col = 0) {
            return new HtmlParseError("missing-whitespace-between-doctype-public-and-system-identifiers", "missing whitespace between doctype public and system identifiers", $line, $col);
        }
        public static function getNestedComment($line = 0, $col = 0) {
            return new HtmlParseError("nested-comment", "nested comment", $line, $col);
        }
        public static function getNoncharacterCharacterReference($line = 0, $col = 0) {
            return new HtmlParseError("noncharacter-character-reference", "noncharacter character reference", $line, $col);
        }
        public static function getNoncharacterInInputStream($line = 0, $col = 0) {
            return new HtmlParseError("noncharacter-in-input-stream", "noncharacter in input stream", $line, $col);
        }
        public static function getNonVoidHtmlElementStartTagWithTrailingSolidus($line = 0, $col = 0) {
            return new HtmlParseError("non-void-html-element-start-tag-with-trailing-solidus", "non void html element start tag with trailing solidus", $line, $col);
        }
        public static function getNullCharacterReference($line = 0, $col = 0) {
            return new HtmlParseError("null-character-reference", "null character reference", $line, $col);
        }
        public static function getSurrogateCharacterReference($line = 0, $col = 0) {
            return new HtmlParseError("surrogate-character-reference", "surrogate character reference", $line, $col);
        }
        public static function getSurrogateInInputStream($line = 0, $col = 0) {
            return new HtmlParseError("surrogate-in-input-stream", "surrogate in input stream", $line, $col);
        }
        public static function getUnexpectedCharacterAfterDoctypeSystemIdentifier($line = 0, $col = 0) {
            return new HtmlParseError("unexpected-character-after-doctype-system-identifier", "unexpected character after doctype system identifier", $line, $col);
        }
        public static function getUnexpectedCharacterInAttributeName($line = 0, $col = 0) {
            return new HtmlParseError("unexpected-character-in-attribute-name", "unexpected character in attribute name", $line, $col);
        }
        public static function getUnexpectedCharacterInUnquotedAttributeValue($line = 0, $col = 0) {
            return new HtmlParseError("unexpected-character-in-unquoted-attribute-value", "unexpected character in unquoted attribute value", $line, $col);
        }
        public static function getUnexpectedEqualsSignBeforeAttributeName($line = 0, $col = 0) {
            return new HtmlParseError("unexpected-equals-sign-before-attribute-name", "unexpected equals sign before attribute name", $line, $col);
        }
        public static function getUnexpectedNullCharacter($line = 0, $col = 0) {
            return new HtmlParseError("unexpected-null-character", "unexpected null character", $line, $col);
        }
        public static function getUnexpectedQuestionMarkInsteadOfTagName($line = 0, $col = 0) {
            return new HtmlParseError("unexpected-question-mark-instead-of-tag-name", "unexpected question mark instead of tag name", $line, $col);
        }
        public static function getUnexpectedSolidusInTag($line = 0, $col = 0) {
            return new HtmlParseError("unexpected-solidus-in-tag", "unexpected solidus in tag", $line, $col);
        }
        public static function getUnknownNamedCharacterReference($line = 0, $col = 0) {
            return new HtmlParseError("unknown-named-character-reference", "unknown named character reference", $line, $col);
        }

    /**
     * @return HtmlParseError
     */
    public static function forCode($code, $col, $line) {
        $method = self::$errors[$code];
        return self::$method($line, $col);
    }

}