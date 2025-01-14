<?php
/** ******************************************************************************* *
  *                    !ATENCION!                                                   *
  *                                                                                 *
  * Este codigo es generado automaticamente. Si lo modificas tus cambios seran      *
  * reemplazados la proxima vez que se autogenere el codigo.                        *
  *                                                                                 *
  * ******************************************************************************* */

namespace OmegaUp\DAO\VO;

/**
 * Value Object class for table `PrivacyStatement_Consent_Log`.
 *
 * @access public
 */
class PrivacyStatementConsentLog extends \OmegaUp\DAO\VO\VO {
    const FIELD_NAMES = [
        'privacystatement_consent_id' => true,
        'identity_id' => true,
        'privacystatement_id' => true,
        'timestamp' => true,
    ];

    function __construct(?array $data = null) {
        if (empty($data)) {
            return;
        }
        $unknownColumns = array_diff_key($data, self::FIELD_NAMES);
        if (!empty($unknownColumns)) {
            throw new \Exception('Unknown columns: ' . join(', ', array_keys($unknownColumns)));
        }
        if (isset($data['privacystatement_consent_id'])) {
            $this->privacystatement_consent_id = (int)$data['privacystatement_consent_id'];
        }
        if (isset($data['identity_id'])) {
            $this->identity_id = (int)$data['identity_id'];
        }
        if (isset($data['privacystatement_id'])) {
            $this->privacystatement_id = (int)$data['privacystatement_id'];
        }
        if (isset($data['timestamp'])) {
            /**
             * @var string|int|float $data['timestamp']
             * @var int $this->timestamp
             */
            $this->timestamp = \OmegaUp\DAO\DAO::fromMySQLTimestamp($data['timestamp']);
        } else {
            $this->timestamp = \OmegaUp\Time::get();
        }
    }

    /**
     * Id del consentimiento de privacidad almacenado en el log
     * Llave Primaria
     * Auto Incremento
     *
     * @var int|null
     */
    public $privacystatement_consent_id = 0;

    /**
     * Identidad del usuario
     *
     * @var int|null
     */
    public $identity_id = null;

    /**
     * Id del documento de privacidad
     *
     * @var int|null
     */
    public $privacystatement_id = null;

    /**
     * Fecha y hora en la que el usuario acepta las nuevas políticas
     *
     * @var int
     */
    public $timestamp;  // CURRENT_TIMESTAMP
}
