<?php

declare(strict_types=1);

namespace Sintezy;

use DateTime;
use Exception;

/**
 * Exceção personalizada para erros da SDK
 */
class SintezySDKException extends Exception
{
    private ?int $statusCode;
    private ?string $errorCode;

    public function __construct(string $message, ?int $statusCode = null, ?string $errorCode = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}

/**
 * Token de autenticação
 */
class AuthToken
{
    public string $accessToken;
    public int $expiresIn;
    public string $tokenType;
    public DateTime $expiresAt;

    public function __construct(string $accessToken, int $expiresIn, string $tokenType)
    {
        $this->accessToken = $accessToken;
        $this->expiresIn = $expiresIn;
        $this->tokenType = $tokenType;
        $this->expiresAt = new DateTime("+{$expiresIn} seconds");
    }
}

/**
 * Dados de uma consulta
 */
class Appointment
{
    public string $secureId;
    public string $status;
    public DateTime $createdAt;
    public string $portalUrl;
    public ?string $title;

    public function __construct(array $data)
    {
        $this->secureId = $data['secureId'];
        $this->status = $data['status'];
        $this->createdAt = new DateTime($data['createdAt']);
        $this->portalUrl = $data['portalUrl'];
        $this->title = $data['title'] ?? null;
    }
}

/**
 * Dados de um documento
 */
class Document
{
    public string $secureId;
    public string $type;
    public mixed $content;
    public DateTime $createdAt;
    public ?DateTime $updatedAt;

    public function __construct(array $data)
    {
        $this->secureId = $data['secureId'];
        $this->type = $data['type'];
        $this->content = $data['content'];
        $this->createdAt = new DateTime($data['createdAt']);
        $this->updatedAt = isset($data['updatedAt']) ? new DateTime($data['updatedAt']) : null;
    }
}

/**
 * Item da lista de documentos
 */
class DocumentListItem
{
    public string $type;
    public bool $exists;
    public ?DateTime $createdAt;

    public function __construct(array $data)
    {
        $this->type = $data['type'];
        $this->exists = $data['exists'];
        $this->createdAt = isset($data['createdAt']) ? new DateTime($data['createdAt']) : null;
    }
}

/**
 * SDK oficial para integração com a plataforma Sintezy.
 * 
 * @example
 * ```php
 * $sdk = new SintezySDK([
 *     'clientId' => 'seu-client-id',
 *     'clientSecret' => 'seu-client-secret',
 * ]);
 * 
 * // Criar consulta (autenticação é automática)
 * $appointment = $sdk->createAppointment([
 *     'userEmail' => 'medico@clinica.com',
 *     'userName' => 'Dr. João Silva',
 *     'layout' => [
 *         'fields' => [
 *             ['name' => 'Queixa Principal', 'content' => '...', 'position' => 0],
 *         ]
 *     ]
 * ]);
 * 
 * // Abrir portal para gravação
 * echo $appointment->portalUrl;
 * ```
 */
class SintezySDK
{
    private const DEFAULT_BASE_URL = 'https://api.sintezy.com';

    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private ?AuthToken $token = null;

    /**
     * @param array $config Configuração da SDK
     *                      - clientId: ID do cliente OAuth
     *                      - clientSecret: Secret do cliente OAuth
     *                      - baseUrl: URL base da API (opcional)
     */
    public function __construct(array $config)
    {
        if (empty($config['clientId']) || empty($config['clientSecret'])) {
            throw new SintezySDKException('clientId and clientSecret are required');
        }

        $this->clientId = $config['clientId'];
        $this->clientSecret = $config['clientSecret'];
        $this->baseUrl = $config['baseUrl'] ?? self::DEFAULT_BASE_URL;
    }

    // ============================================================
    // AUTENTICAÇÃO
    // ============================================================

    /**
     * Autentica a aplicação usando OAuth 2.0 Client Credentials.
     *
     * @return AuthToken Token de acesso
     * @throws SintezySDKException
     */
    public function authenticate(): AuthToken
    {
        $response = $this->httpRequest('POST', '/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ], false);

        $this->token = new AuthToken(
            $response['access_token'],
            $response['expires_in'],
            $response['token_type']
        );

        return $this->token;
    }

    /**
     * Verifica se está autenticado e se o token ainda é válido.
     */
    public function isAuthenticated(): bool
    {
        if ($this->token === null) {
            return false;
        }
        // Considera expirado se faltar menos de 60 segundos
        $threshold = new DateTime('+60 seconds');
        return $this->token->expiresAt > $threshold;
    }

    /**
     * Retorna o token atual (ou null se não autenticado).
     */
    public function getToken(): ?AuthToken
    {
        return $this->token;
    }

    private function ensureAuthenticated(): AuthToken
    {
        if (!$this->isAuthenticated()) {
            return $this->authenticate();
        }
        return $this->token;
    }

    // ============================================================
    // APPOINTMENTS (CONSULTAS)
    // ============================================================

    /**
     * Cria uma nova consulta (appointment).
     *
     * @param array $params Parâmetros da consulta
     *                      - userEmail: Email do usuário (obrigatório)
     *                      - userName: Nome do usuário (obrigatório)
     *                      - layout: Layout da consulta (obrigatório)
     *                      - redirectUrl: URL de redirecionamento após geração do documento (opcional).
     *                        Se fornecida, o portal redireciona para esta URL ao invés de fechar a janela.
     * @return Appointment
     * @throws SintezySDKException
     */
    public function createAppointment(array $params): Appointment
    {
        $data = $this->request('POST', '/sdk/appointments', $params);
        return new Appointment($data);
    }

    /**
     * Busca uma consulta pelo ID.
     *
     * @param string $appointmentId ID seguro da consulta
     * @return Appointment
     * @throws SintezySDKException
     */
    public function getAppointment(string $appointmentId): Appointment
    {
        $data = $this->request('GET', "/sdk/appointments/{$appointmentId}");
        return new Appointment($data);
    }

    /**
     * Exclui uma consulta (soft delete).
     *
     * @param string $appointmentId ID seguro da consulta
     * @return array Confirmação da exclusão
     * @throws SintezySDKException
     */
    public function deleteAppointment(string $appointmentId): array
    {
        return $this->request('DELETE', "/sdk/appointments/{$appointmentId}");
    }

    // ============================================================
    // TRANSCRIPTION (TRANSCRIÇÃO)
    // ============================================================

    /**
     * Busca a transcrição de uma consulta.
     *
     * @param string $appointmentId ID seguro da consulta
     * @return array Transcrição (secureId, transcription, recordedTimeSeconds?, status)
     * @throws SintezySDKException
     */
    public function getTranscription(string $appointmentId): array
    {
        return $this->request('GET', "/sdk/appointments/{$appointmentId}/transcription");
    }

    // ============================================================
    // SUBSCRIPTION STATUS (ASSINATURA)
    // ============================================================

    /**
     * Consulta o status da assinatura de um email.
     * Disponível apenas para API Keys do tipo unauthenticated (reseller).
     *
     * @param string $email Email do usuário a consultar
     * @return array Status da assinatura (email, hasSubscription, status?, planType?, endDate?, checkoutUrl?)
     * @throws SintezySDKException
     */
    public function getSubscriptionStatus(string $email): array
    {
        return $this->request('GET', '/sdk/subscription-status?email=' . urlencode($email));
    }

    // ============================================================
    // DOCUMENTOS
    // ============================================================

    /**
     * Gera um documento a partir de uma consulta.
     *
     * @param string $appointmentId ID seguro da consulta
     * @param string $documentType Tipo do documento
     * @return Document
     * @throws SintezySDKException
     */
    public function generateDocument(string $appointmentId, string $documentType): Document
    {
        $data = $this->request('POST', "/sdk/appointments/{$appointmentId}/documents", [
            'documentType' => $documentType,
        ]);
        return new Document($data);
    }

    /**
     * Busca um documento específico de uma consulta.
     *
     * @param string $appointmentId ID seguro da consulta
     * @param string $documentType Tipo do documento
     * @return Document
     * @throws SintezySDKException
     */
    public function getDocument(string $appointmentId, string $documentType): Document
    {
        $data = $this->request('GET', "/sdk/appointments/{$appointmentId}/documents/{$documentType}");
        return new Document($data);
    }

    /**
     * Lista todos os documentos de uma consulta.
     *
     * @param string $appointmentId ID seguro da consulta
     * @return DocumentListItem[]
     * @throws SintezySDKException
     */
    public function listDocuments(string $appointmentId): array
    {
        $data = $this->request('GET', "/sdk/appointments/{$appointmentId}/documents");
        return array_map(fn($item) => new DocumentListItem($item), $data);
    }

    // ============================================================
    // HELPERS INTERNOS
    // ============================================================

    private function request(string $method, string $path, ?array $body = null): array
    {
        $this->ensureAuthenticated();
        return $this->httpRequest($method, $path, $body, true);
    }

    private function httpRequest(string $method, string $path, ?array $body = null, bool $withAuth = true): array
    {
        $url = $this->baseUrl . $path;

        $headers = ['Content-Type: application/json'];
        if ($withAuth && $this->token !== null) {
            $headers[] = "Authorization: Bearer {$this->token->accessToken}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new SintezySDKException("Connection error: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new SintezySDKException(
                $data['message'] ?? "Request failed: {$method} {$path}",
                $httpCode,
                $data['code'] ?? null
            );
        }

        return $data;
    }
}
