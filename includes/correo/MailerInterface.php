<?php
/**
 * Transporte de correo. El resto de la app arma el mensaje y no sabe si sale por
 * SMTP, por la API de un proveedor o si solo se simula.
 *
 * Mensaje:
 *   to, to_name, subject, html, text,
 *   reply_to?  (string),
 *   inline?    list<array{path:string, cid:string, name:string}>  imágenes referenciadas como cid:
 */
interface MailerInterface
{
    public function nombre(): string;

    /**
     * @param array<string,mixed> $mensaje
     * @return array{ok:bool, message_id?:string, error?:string, archivo?:string}
     */
    public function enviar(array $mensaje): array;
}
