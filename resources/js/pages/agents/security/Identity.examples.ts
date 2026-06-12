// Generated bundle of sign-in token examples shown on the agent Security tab.
// Two equivalent schemes (JWT + HMAC), each demonstrated across the 10 most-
// used backend languages. The literal `__AGENT_EXTERNAL_ID__` in JWT examples
// is substituted at display time with the current agent's external id.

export type TokenExample = {
    id: string;
    name: string;
    hljs: string;
    hint: string;
    code: string;
};

export const JWT_EXAMPLES: TokenExample[] = [
    {
        id: 'javascript',
        name: 'JavaScript',
        hljs: 'javascript',
        hint: 'Using the jsonwebtoken package.',
        code: `// Keep YOUR_SIGNING_SECRET on the server — never expose it to the browser.
const jwt = require('jsonwebtoken');

function signChatviaToken(userId, ttlSeconds = 3600) {
  const now = Math.floor(Date.now() / 1000);
  const payload = {
    sub: userId,
    aud: '__AGENT_EXTERNAL_ID__',
    exp: now + ttlSeconds,
  };
  return jwt.sign(payload, 'YOUR_SIGNING_SECRET', { algorithm: 'HS256' });
}

console.log(signChatviaToken('user_123'));`,
    },
    {
        id: 'python',
        name: 'Python',
        hljs: 'python',
        hint: 'Using the PyJWT package (pip install PyJWT).',
        code: `import time
import jwt  # pip install PyJWT

# Keep YOUR_SIGNING_SECRET on the server only — never ship it to the browser.
SECRET = "YOUR_SIGNING_SECRET"


def sign_chatvia_token(user_id: str, ttl_seconds: int = 3600) -> str:
    payload = {
        "sub": user_id,
        "aud": "__AGENT_EXTERNAL_ID__",
        "exp": int(time.time()) + ttl_seconds,
    }
    return jwt.encode(payload, SECRET, algorithm="HS256")


print(sign_chatvia_token("user_123"))`,
    },
    {
        id: 'java',
        name: 'Java',
        hljs: 'java',
        hint: 'Using the auth0 java-jwt library (com.auth0:java-jwt).',
        code: `import com.auth0.jwt.JWT;
import com.auth0.jwt.algorithms.Algorithm;
import java.time.Instant;
import java.util.Date;

public class ChatviaJwt {
    // Keep YOUR_SIGNING_SECRET on the server. Never ship it to the browser.
    private static final String SECRET = "YOUR_SIGNING_SECRET";

    public static String signChatviaToken(String userId, long ttlSeconds) {
        Algorithm algorithm = Algorithm.HMAC256(SECRET);
        return JWT.create()
                .withSubject(userId)
                .withAudience("__AGENT_EXTERNAL_ID__")
                .withExpiresAt(Date.from(Instant.now().plusSeconds(ttlSeconds)))
                .sign(algorithm);
    }

    public static void main(String[] args) {
        System.out.println(signChatviaToken("user_123", 3600));
    }
}`,
    },
    {
        id: 'csharp',
        name: 'C#',
        hljs: 'csharp',
        hint: 'Using System.IdentityModel.Tokens.Jwt with Microsoft.IdentityModel.Tokens.',
        code: `using System;
using System.IdentityModel.Tokens.Jwt;
using System.Security.Claims;
using System.Text;
using Microsoft.IdentityModel.Tokens;

// Keep YOUR_SIGNING_SECRET on the server. Never expose it to the browser.
static class Chatvia
{
    public static string SignChatviaToken(string userId, int ttlSeconds = 3600)
    {
        var key = new SymmetricSecurityKey(Encoding.UTF8.GetBytes("YOUR_SIGNING_SECRET"));
        var creds = new SigningCredentials(key, SecurityAlgorithms.HmacSha256);

        var token = new JwtSecurityToken(
            audience: "__AGENT_EXTERNAL_ID__",
            claims: new[] { new Claim(JwtRegisteredClaimNames.Sub, userId) },
            expires: DateTime.UtcNow.AddSeconds(ttlSeconds),
            signingCredentials: creds);

        return new JwtSecurityTokenHandler().WriteToken(token);
    }

    static void Main() => Console.WriteLine(SignChatviaToken("user_123"));
}`,
    },
    {
        id: 'cpp',
        name: 'C++',
        hljs: 'cpp',
        hint: 'Using the header-only jwt-cpp library.',
        code: `#include <jwt-cpp/jwt.h>
#include <iostream>
#include <string>

// Keep YOUR_SIGNING_SECRET on the server only — never ship it to the browser.
std::string signChatviaToken(const std::string& userId, long ttlSeconds = 3600) {
    auto now = std::chrono::system_clock::now();
    return jwt::create()
        .set_subject(userId)
        .set_audience("__AGENT_EXTERNAL_ID__")
        .set_expires_at(now + std::chrono::seconds{ttlSeconds})
        .sign(jwt::algorithm::hs256{"YOUR_SIGNING_SECRET"});
}

int main() {
    std::cout << signChatviaToken("user_123") << std::endl;
}`,
    },
    {
        id: 'php',
        name: 'PHP',
        hljs: 'php',
        hint: 'Using the firebase/php-jwt package (composer require firebase/php-jwt).',
        code: `<?php

require 'vendor/autoload.php';

use Firebase\\JWT\\JWT;

// Keep YOUR_SIGNING_SECRET on the server — never expose it to the browser.

function signChatviaToken(string $userId, int $ttlSeconds = 3600): string
{
    $payload = [
        'sub' => $userId,
        'aud' => '__AGENT_EXTERNAL_ID__',
        'exp' => time() + $ttlSeconds,
    ];

    return JWT::encode($payload, 'YOUR_SIGNING_SECRET', 'HS256');
}

echo signChatviaToken('user_123'), PHP_EOL;`,
    },
    {
        id: 'go',
        name: 'Go',
        hljs: 'go',
        hint: 'Using the github.com/golang-jwt/jwt/v5 package.',
        code: `package main

import (
	"fmt"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

// Keep YOUR_SIGNING_SECRET on the server only — never expose it to the browser.
const signingSecret = "YOUR_SIGNING_SECRET"

func signChatviaToken(userId string, ttlSeconds int64) (string, error) {
	claims := jwt.MapClaims{
		"sub": userId,
		"aud": "__AGENT_EXTERNAL_ID__",
		"exp": time.Now().Add(time.Duration(ttlSeconds) * time.Second).Unix(),
	}
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	return token.SignedString([]byte(signingSecret))
}

func main() {
	tok, _ := signChatviaToken("user_123", 3600)
	fmt.Println(tok)
}`,
    },
    {
        id: 'rust',
        name: 'Rust',
        hljs: 'rust',
        hint: 'Using the jsonwebtoken crate with serde for claim serialization.',
        code: `use jsonwebtoken::{encode, EncodingKey, Header};
use serde::Serialize;
use std::time::{SystemTime, UNIX_EPOCH};

// Keep YOUR_SIGNING_SECRET on the server. NEVER expose it to the browser.
#[derive(Serialize)]
struct Claims<'a> {
    sub: &'a str,
    aud: &'a str,
    exp: u64,
}

fn sign_chatvia_token(user_id: &str, ttl_seconds: u64) -> String {
    let exp = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .unwrap()
        .as_secs()
        + ttl_seconds;

    let claims = Claims {
        sub: user_id,
        aud: "__AGENT_EXTERNAL_ID__",
        exp,
    };

    encode(
        &Header::default(),
        &claims,
        &EncodingKey::from_secret(b"YOUR_SIGNING_SECRET"),
    )
    .unwrap()
}

fn main() {
    println!("{}", sign_chatvia_token("user_123", 3600));
}`,
    },
    {
        id: 'kotlin',
        name: 'Kotlin',
        hljs: 'kotlin',
        hint: 'Using the Auth0 java-jwt library (com.auth0:java-jwt).',
        code: `import com.auth0.jwt.JWT
import com.auth0.jwt.algorithms.Algorithm
import java.util.Date

// Keep YOUR_SIGNING_SECRET on the server only. NEVER expose it to the browser.
private const val SIGNING_SECRET = "YOUR_SIGNING_SECRET"

fun signChatviaToken(userId: String, ttlSeconds: Long = 3600): String {
    val algorithm = Algorithm.HMAC256(SIGNING_SECRET)
    return JWT.create()
        .withSubject(userId)
        .withAudience("__AGENT_EXTERNAL_ID__")
        .withExpiresAt(Date(System.currentTimeMillis() + ttlSeconds * 1000))
        .sign(algorithm)
}

fun main() {
    println(signChatviaToken("user_123"))
}`,
    },
    {
        id: 'swift',
        name: 'Swift',
        hljs: 'swift',
        hint: 'Using JWTKit (vapor/jwt-kit) — add `.package(url: "https://github.com/vapor/jwt-kit.git", from: "5.0.0")` to Package.swift.',
        code: `import Foundation
import JWTKit

// Keep YOUR_SIGNING_SECRET on the server. NEVER expose it to the browser.

struct ChatviaPayload: JWTPayload {
    var sub: SubjectClaim
    var aud: AudienceClaim
    var exp: ExpirationClaim

    func verify(using algorithm: some JWTAlgorithm) async throws {
        try self.exp.verifyNotExpired()
    }
}

func signChatviaToken(_ userId: String, ttlSeconds: Int = 3600) async throws -> String {
    let keys = JWTKeyCollection()
    await keys.add(hmac: "YOUR_SIGNING_SECRET", digestAlgorithm: .sha256)
    let payload = ChatviaPayload(
        sub: SubjectClaim(value: userId),
        aud: AudienceClaim(value: "__AGENT_EXTERNAL_ID__"),
        exp: ExpirationClaim(value: Date().addingTimeInterval(TimeInterval(ttlSeconds)))
    )
    return try await keys.sign(payload)
}

print(try await signChatviaToken("user_123"))`,
    },
];

export const HMAC_EXAMPLES: TokenExample[] = [
    {
        id: 'javascript',
        name: 'JavaScript',
        hljs: 'javascript',
        hint: "Using Node's built-in crypto module — no extra dependency.",
        code: `// Keep YOUR_SIGNING_SECRET on the server — never ship it to the browser.
const crypto = require('crypto');

function signChatviaToken(userId, ttlSeconds = 3600) {
  const exp = Math.floor(Date.now() / 1000) + ttlSeconds;
  const message = \`\${userId}.\${exp}\`;
  const sig = crypto
    .createHmac('sha256', 'YOUR_SIGNING_SECRET')
    .update(message)
    .digest('hex');
  return \`cvh1.\${userId}.\${exp}.\${sig}\`;
}

console.log(signChatviaToken('user_123'));`,
    },
    {
        id: 'python',
        name: 'Python',
        hljs: 'python',
        hint: "Using Python's built-in hmac and hashlib modules — no extra dependency.",
        code: `import hmac
import hashlib
import time

# Keep YOUR_SIGNING_SECRET on the server only — never ship it to the browser.
SECRET = "YOUR_SIGNING_SECRET"


def sign_chatvia_token(user_id: str, ttl_seconds: int = 3600) -> str:
    exp = int(time.time()) + ttl_seconds
    message = f"{user_id}.{exp}"
    sig = hmac.new(SECRET.encode(), message.encode(), hashlib.sha256).hexdigest()
    return f"cvh1.{user_id}.{exp}.{sig}"


print(sign_chatvia_token("user_123"))`,
    },
    {
        id: 'java',
        name: 'Java',
        hljs: 'java',
        hint: "Using Java's built-in javax.crypto.Mac with HmacSHA256 — no extra dependency.",
        code: `import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.time.Instant;

public class ChatviaHmac {
    // Keep YOUR_SIGNING_SECRET on the server. Never ship it to the browser.
    private static final String SECRET = "YOUR_SIGNING_SECRET";

    public static String signChatviaToken(String userId, long ttlSeconds) throws Exception {
        long exp = Instant.now().getEpochSecond() + ttlSeconds;
        String message = userId + "." + exp;

        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec(SECRET.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
        byte[] raw = mac.doFinal(message.getBytes(StandardCharsets.UTF_8));

        StringBuilder sig = new StringBuilder(raw.length * 2);
        for (byte b : raw) sig.append(String.format("%02x", b));

        return "cvh1." + userId + "." + exp + "." + sig;
    }

    public static void main(String[] args) throws Exception {
        System.out.println(signChatviaToken("user_123", 3600));
    }
}`,
    },
    {
        id: 'csharp',
        name: 'C#',
        hljs: 'csharp',
        hint: "Using .NET's built-in System.Security.Cryptography.HMACSHA256 — no extra dependency.",
        code: `using System;
using System.Security.Cryptography;
using System.Text;

// Keep YOUR_SIGNING_SECRET on the server. Never ship it to the browser.
static class Chatvia
{
    public static string SignChatviaToken(string userId, int ttlSeconds = 3600)
    {
        const string secret = "YOUR_SIGNING_SECRET";
        long exp = DateTimeOffset.UtcNow.ToUnixTimeSeconds() + ttlSeconds;
        string message = $"{userId}.{exp}";

        using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
        byte[] hash = hmac.ComputeHash(Encoding.UTF8.GetBytes(message));
        string sig = Convert.ToHexString(hash).ToLowerInvariant();

        return $"cvh1.{userId}.{exp}.{sig}";
    }

    static void Main() => Console.WriteLine(SignChatviaToken("user_123"));
}`,
    },
    {
        id: 'cpp',
        name: 'C++',
        hljs: 'cpp',
        hint: "Using OpenSSL's HMAC() — link with -lcrypto.",
        code: `#include <openssl/hmac.h>
#include <iomanip>
#include <sstream>
#include <string>
#include <ctime>
#include <iostream>

// Keep YOUR_SIGNING_SECRET on the server only — never ship it to the browser.
std::string signChatviaToken(const std::string& userId, long ttlSeconds = 3600) {
    const std::string secret = "YOUR_SIGNING_SECRET";
    long exp = std::time(nullptr) + ttlSeconds;
    std::string message = userId + "." + std::to_string(exp);

    unsigned char mac[EVP_MAX_MD_SIZE];
    unsigned int macLen = 0;
    HMAC(EVP_sha256(), secret.data(), secret.size(),
         reinterpret_cast<const unsigned char*>(message.data()), message.size(),
         mac, &macLen);

    std::ostringstream hex;
    hex << std::hex << std::setfill('0');
    for (unsigned int i = 0; i < macLen; ++i) hex << std::setw(2) << static_cast<int>(mac[i]);

    return "cvh1." + userId + "." + std::to_string(exp) + "." + hex.str();
}

int main() {
    std::cout << signChatviaToken("user_123") << std::endl;
}`,
    },
    {
        id: 'php',
        name: 'PHP',
        hljs: 'php',
        hint: "Using PHP's built-in hash_hmac — no extra dependency.",
        code: `<?php

// Keep YOUR_SIGNING_SECRET on the server — never expose it to the browser.
// Uses PHP's built-in hash_hmac; no extra dependency required.

function signChatviaToken(string $userId, int $ttlSeconds = 3600): string
{
    $secret = 'YOUR_SIGNING_SECRET';
    $exp = time() + $ttlSeconds;
    $message = "{$userId}.{$exp}";
    $sig = hash_hmac('sha256', $message, $secret);

    return "cvh1.{$userId}.{$exp}.{$sig}";
}

echo signChatviaToken('user_123'), PHP_EOL;`,
    },
    {
        id: 'go',
        name: 'Go',
        hljs: 'go',
        hint: "Using Go's built-in crypto/hmac and crypto/sha256 — no extra dependency.",
        code: `package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"time"
)

// Keep YOUR_SIGNING_SECRET on the server only — never ship it to the browser.
const signingSecret = "YOUR_SIGNING_SECRET"

func signChatviaToken(userId string, ttlSeconds int64) string {
	exp := time.Now().Unix() + ttlSeconds
	msg := fmt.Sprintf("%s.%d", userId, exp)
	mac := hmac.New(sha256.New, []byte(signingSecret))
	mac.Write([]byte(msg))
	sig := hex.EncodeToString(mac.Sum(nil))
	return fmt.Sprintf("cvh1.%s.%d.%s", userId, exp, sig)
}

func main() {
	fmt.Println(signChatviaToken("user_123", 3600))
}`,
    },
    {
        id: 'rust',
        name: 'Rust',
        hljs: 'rust',
        hint: 'Using the hmac and sha2 crates (with hex for encoding).',
        code: `use hmac::{Hmac, Mac};
use sha2::Sha256;
use std::time::{SystemTime, UNIX_EPOCH};

type HmacSha256 = Hmac<Sha256>;

// Keep YOUR_SIGNING_SECRET on the server. NEVER ship it to the browser.
fn sign_chatvia_token(user_id: &str, ttl_seconds: u64) -> String {
    let exp = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .unwrap()
        .as_secs()
        + ttl_seconds;

    let message = format!("{}.{}", user_id, exp);

    let mut mac = HmacSha256::new_from_slice(b"YOUR_SIGNING_SECRET").unwrap();
    mac.update(message.as_bytes());
    let sig = hex::encode(mac.finalize().into_bytes());

    format!("cvh1.{}.{}.{}", user_id, exp, sig)
}

fn main() {
    println!("{}", sign_chatvia_token("user_123", 3600));
}`,
    },
    {
        id: 'kotlin',
        name: 'Kotlin',
        hljs: 'kotlin',
        hint: "Using Kotlin's built-in javax.crypto.Mac with HmacSHA256 — no extra dependency.",
        code: `import javax.crypto.Mac
import javax.crypto.spec.SecretKeySpec

// Keep YOUR_SIGNING_SECRET on the server only. NEVER expose it to the browser.
private const val SIGNING_SECRET = "YOUR_SIGNING_SECRET"

fun signChatviaToken(userId: String, ttlSeconds: Long = 3600): String {
    val exp = System.currentTimeMillis() / 1000 + ttlSeconds
    val message = "$userId.$exp"
    val mac = Mac.getInstance("HmacSHA256").apply {
        init(SecretKeySpec(SIGNING_SECRET.toByteArray(Charsets.UTF_8), "HmacSHA256"))
    }
    val sig = mac.doFinal(message.toByteArray(Charsets.UTF_8))
        .joinToString("") { "%02x".format(it.toInt() and 0xFF) }
    return "cvh1.$userId.$exp.$sig"
}

fun main() {
    println(signChatviaToken("user_123"))
}`,
    },
    {
        id: 'swift',
        name: 'Swift',
        hljs: 'swift',
        hint: "Using Apple's CryptoKit framework — built into Swift on Apple platforms.",
        code: `import Foundation
import CryptoKit

// Keep YOUR_SIGNING_SECRET on the server. NEVER expose it to the browser.

func signChatviaToken(_ userId: String, ttlSeconds: Int = 3600) -> String {
    let exp = Int(Date().timeIntervalSince1970) + ttlSeconds
    let message = "\\(userId).\\(exp)"
    let key = SymmetricKey(data: Data("YOUR_SIGNING_SECRET".utf8))
    let mac = HMAC<SHA256>.authenticationCode(for: Data(message.utf8), using: key)
    let sig = mac.map { String(format: "%02x", $0) }.joined()
    return "cvh1.\\(userId).\\(exp).\\(sig)"
}

print(signChatviaToken("user_123"))`,
    },
];
