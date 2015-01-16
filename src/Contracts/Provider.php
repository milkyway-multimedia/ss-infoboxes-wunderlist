<?php
/**
 * Milkyway Multimedia
 * Provider.php
 *
 * @package milkywaymultimedia.com.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

namespace Milkyway\SS\InfoBoxes\Wunderlist\Contracts;


interface Provider {
    public function get($action, $vars = []);
}