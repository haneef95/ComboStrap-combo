<?php


use ComboStrap\Call;
use ComboStrap\CallStack;
use ComboStrap\MediaLink;
use ComboStrap\TagAttributes;

class action_plugin_combo_headingatx extends DokuWiki_Action_Plugin
{

    public function register(\Doku_Event_Handler $controller)
    {
        /**
         * Found in {@link Doku_Handler::finalize()}
         *
         * Doc: https://www.dokuwiki.org/devel:event:parser_handler_done
         */
        $controller->register_hook(
            'PARSER_HANDLER_DONE',
            'AFTER',
            $this,
            '_post_process_heading',
            array()
        );

    }


    /**
     * Transform the special heading atx call
     * in an enter and exit heading atx calls
     *
     * Code extracted and adapted from the end of {@link Doku_Handler::header()}
     *
     * @param   $event Doku_Event
     */
    function _post_process_heading(&$event, $param)
    {
        /**
         * @var Doku_Handler $handler
         */
        $handler = $event->data;
        $callStack = CallStack::createFromHandler($handler);
        $callStack->moveToStart();

        /**
         * Processing variable about the context
         */
        $actualHeadingState = DOKU_LEXER_EXIT; // enter if we have entered a heading, exit otherwise
        $actualSectionState = null; // enter if we have created a section
        $headingEnterCall = null; // the enter call
        $lastEndPosition = null; // the last end position to close the section if any
        $headingText = ""; // text only content in the heading
        while ($actualCall = $callStack->next()) {
            $componentName = $actualCall->getTagName();
            if (
                ($lastEndPosition != null && $actualCall->getFirstMatchedCharacterPosition() >= $lastEndPosition)
                || $lastEndPosition == null
            ) {
                // p_open and p_close have always a position value of 0, we filter them
                $lastEndPosition = $actualCall->getLastMatchedCharacterPosition();
            }
            if ($componentName == syntax_plugin_combo_headingatx::TAG) {
                $actualCall->setState(DOKU_LEXER_ENTER);
                $actualHeadingState = DOKU_LEXER_ENTER;
                $headingEnterCall = $callStack->getActualCall();
                if ($actualCall->getContext() == syntax_plugin_combo_heading::TYPE_OUTLINE) {
                    if ($handler->getStatus('section')) {
                        $callStack->insertBefore(
                            Call::createNativeCall(
                                'section_close',
                                array(),
                                $actualCall->getLastMatchedCharacterPosition()
                            )
                        );
                        $actualSectionState = DOKU_LEXER_EXIT;
                    }
                }
                continue;
            }
            if ($actualHeadingState == DOKU_LEXER_ENTER) {
                // we are in a heading description
                switch ($actualCall->getComponentName()) {
                    case "internalmedia":
                        // no link for media in heading
                        $actualCall->getCall()[1][6] = MediaLink::LINKING_NOLINK_VALUE;
                        continue 2;
                    case syntax_plugin_combo_media::COMPONENT:
                        // no link for media in heading
                        $actualCall->addAttribute(TagAttributes::LINKING_KEY, MediaLink::LINKING_NOLINK_VALUE);
                        continue 2;
                    case "p_open":
                        $callStack->deleteActualCallAndPrevious();
                        continue 2;
                    case "p_close":

                        /**
                         * Create the exit call
                         * and open the section
                         * Code extracted and adapted from the end of {@link Doku_Handler::header()}
                         */
                        $callStack->insertBefore(
                            Call::createComboCall(
                                syntax_plugin_combo_headingatx::TAG,
                                DOKU_LEXER_EXIT,
                                $headingEnterCall->getAttributes()
                            )
                        );

                        if ($headingEnterCall->getContext() == syntax_plugin_combo_heading::TYPE_OUTLINE) {
                            /**
                             * Update the entering call with the text capture
                             */
                            $headingEnterCall->addAttribute(syntax_plugin_combo_heading::HEADING_TEXT_ATTRIBUTE, $headingText);
                            $headingText = "";


                            $callStack->insertBefore(
                                Call::createNativeCall(
                                    'section_open',
                                    array($headingEnterCall->getAttribute(syntax_plugin_combo_headingatx::LEVEL)),
                                    $headingEnterCall->getFirstMatchedCharacterPosition()
                                )
                            );
                            $handler->setStatus('section', true);
                            $actualHeadingState = DOKU_LEXER_EXIT;
                            $actualSectionState = DOKU_LEXER_ENTER;

                        }

                        /**
                         * Delete the p_close
                         * and set the parsing state to not enter
                         */
                        $callStack->deleteActualCallAndPrevious();


                        continue 2;
                    default:
                        if ($actualHeadingState == DOKU_LEXER_UNMATCHED) {
                            $actualCall->setComboComponent(syntax_plugin_combo_headingatx::TAG);
                        }
                        // only cdata for now
                        // no image, ...
                        if ($componentName == "cdata") {
                            if ($headingText != "") {
                                $headingText .= " ";
                            }
                            $headingText .= trim($actualCall->getMatchedContent());
                        }
                }
            }
            /**
             * when a heading of dokuwiki is mixed with
             * an atx heading, there is already a section close
             * at the end or in the middle
             */
            if ($actualCall->getComponentName() == "section_close") {
                $actualSectionState = DOKU_LEXER_EXIT;
            }
        }

        /**
         * If the section was open by us or is still open, we close it
         *
         * We don't use the standard `section` key (ie  $handler->getStatus('section')
         * because it's open when we receive the handler
         * even if the `section_close` is present in the call stack
         *
         * We make sure that we close only what we have open
         */
        if ($actualSectionState == DOKU_LEXER_ENTER) {
            $handler->setStatus('section', false);
            $callStack->insertAfter(
                Call::createNativeCall(
                    'section_close',
                    array(),
                    $lastEndPosition
                )
            );
        }


    }
}
