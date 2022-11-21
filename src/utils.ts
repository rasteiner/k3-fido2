
/**
 * Convert a ArrayBuffer to Base64
 * @param {ArrayBuffer} buffer
 * @returns {String}
 */
export function arrayBufferToBase64(buffer) {
  let binary = '';
  let bytes = new Uint8Array(buffer);
  let len = bytes.byteLength;
  for (let i = 0; i < len; i++) {
    binary += String.fromCharCode( bytes[ i ] );
  }
  return window.btoa(binary);
}

/**
 * decode base64url to ArrayBuffer
 */
export function b64ToArrayBuffer(source) {
  // Convert base64url to base64
  let encoded = source.replace(/-/g, '+').replace(/_/g, '/');

  // Add padding
  encoded += '='.repeat((4 - encoded.length % 4) % 4);

  return Uint8Array.from(atob(encoded), c => c.charCodeAt(0)).buffer;
}
