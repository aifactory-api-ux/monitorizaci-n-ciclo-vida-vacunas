<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Falta de un dato de referencia necesario para operar.
 *
 * Indica un problema de configuración o de carga de datos maestros, no un
 * error del solicitante, por lo que la capa Api responde 500 con un mensaje
 * explícito en lugar de un 422.
 */
class ConfigurationException extends DomainException
{
    /**
     * Código de error estable para el cliente.
     */
    public const ERROR_CODE = 'configuration_error';

    public function __construct(string $message, string $errorCode = self::ERROR_CODE)
    {
        parent::__construct($message, $errorCode);
    }

    /**
     * Campus de referencia ausente en el catálogo.
     */
    public static function missingCampus(string $campusName): self
    {
        return new self(
            sprintf('El campus "%s" no está dado de alta en el catálogo de campus.', $campusName),
            'missing_campus',
        );
    }

    /**
     * Integración con LIMS Labware deshabilitada en la configuración.
     */
    public static function limsIntegrationDisabled(): self
    {
        return new self(
            'La integración con LIMS Labware está deshabilitada: habilítela con LIMS_ENABLED para leer resultados.',
            'lims_integration_disabled',
        );
    }

    /**
     * Integración con LIMS Labware habilitada sin la configuración necesaria.
     */
    public static function missingLimsConfiguration(string $missing): self
    {
        return new self(
            sprintf(
                'La integración con LIMS Labware no puede arrancar: falta la configuración "%s" en config/lims.php.',
                $missing,
            ),
            'missing_lims_configuration',
        );
    }

    /**
     * Canal de notificación habilitado sin destinatarios configurados.
     */
    public static function missingNotificationRecipients(string $channel): self
    {
        return new self(
            sprintf(
                'El canal de notificación "%s" no puede arrancar: faltan los destinatarios en config/monitoring.php.',
                $channel,
            ),
            'missing_notification_recipients',
        );
    }

    /**
     * Credencial ausente en el gestor de secretos del despliegue.
     *
     * El mensaje identifica la ruta del secreto y nunca su valor, para no
     * filtrar credenciales en los registros (véase D16).
     */
    public static function missingSecret(string $path): self
    {
        return new self(
            sprintf(
                'Falta la credencial "%s" en el gestor de secretos: no hay ningún valor de reserva (véase D16).',
                $path,
            ),
            'missing_secret',
        );
    }

    /**
     * Cliente de contraseña de Passport ausente en la base de datos.
     */
    public static function missingPasswordClient(): self
    {
        return new self(
            'No hay ningún cliente de contraseña de Passport activo: créelo con `php artisan passport:client --password`.',
            'missing_password_client',
        );
    }

    /**
     * Umbral de monitorización configurado con un valor no utilizable.
     */
    public static function invalidMonitoringThreshold(string $group, string $key): self
    {
        return new self(
            sprintf(
                'El umbral de monitorización "%s.%s" de config/monitoring.php debe ser un número mayor que cero.',
                $group,
                $key,
            ),
            'invalid_monitoring_threshold',
        );
    }
}
