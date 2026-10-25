<?php
// TEST ONLY. Does not acknowledge or change the variable: captures the forwarded action.
class FixtureActionTarget extends IPSModule
{
    public function RequestAction($ident, $value)
    {
        $GLOBALS['instanceRequest'] = ['ident' => $ident, 'value' => $value];
    }
}
