<?php
declare(strict_types=1);

namespace BcPasskeyAuth\Model\Entity;

use Cake\ORM\Entity;

/**
 * PasskeyCredential Entity
 *
 * @property int           $id
 * @property int           $user_id
 * @property string        $prefix
 * @property string        $user_handle
 * @property string        $credential_id
 * @property string        $public_key
 * @property int           $counter
 * @property string|null   $transports
 * @property string|null   $aaguid
 * @property string|null   $name
 * @property \Cake\I18n\FrozenTime|null $last_used
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 */
class PasskeyCredential extends Entity
{
    protected array $_accessible = [
        'user_id'       => true,
        'prefix'        => true,
        'user_handle'   => true,
        'credential_id' => true,
        'public_key'    => true,
        'counter'       => true,
        'transports'    => true,
        'aaguid'        => true,
        'name'          => true,
        'last_used'     => true,
        'created'       => true,
        'modified'      => true,
    ];
}
