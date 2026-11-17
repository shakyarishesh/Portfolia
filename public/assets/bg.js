// three.js background: a wave field of points in the accent colours.
// Multi-colour ripples spread from the mouse, swings the camera per section, follows the theme.
import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';

THREE.ColorManagement.enabled = false; // CSS hex colours go straight to the shader

const root = document.documentElement;
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const W = 60, D = 30, COLS = 180, ROWS = 90;

// if WebGL or the CDN fails, this module stops here and the CSS blobs stay as the background
const renderer = new THREE.WebGLRenderer({ antialias: false, alpha: true, powerPreference: 'low-power' });
renderer.setPixelRatio(Math.min(devicePixelRatio, 1.5));
renderer.domElement.className = 'bg-3d';
document.querySelector('.bg').prepend(renderer.domElement);
document.querySelectorAll('.blob').forEach(b => b.remove());

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(60, 1, 0.1, 100);

const pos = new Float32Array(COLS * ROWS * 3);
for (let i = 0, k = 0; i < COLS; i++) for (let j = 0; j < ROWS; j++) {
  pos[k++] = (i / (COLS - 1) - 0.5) * W;
  pos[k++] = 0;
  pos[k++] = (j / (ROWS - 1)) * -D + 6;
}
const geo = new THREE.BufferGeometry();
geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));

const uniforms = {
  uTime: { value: 0 },
  uMouse: { value: new THREE.Vector3(0, 0, -6) }, // ripple centre until the mouse moves
  uSize: { value: 2.2 * renderer.getPixelRatio() },
  uAlpha: { value: 0.8 },
  uA1: { value: new THREE.Color() },
  uA2: { value: new THREE.Color() },
  uA3: { value: new THREE.Color() },
};

const mat = new THREE.ShaderMaterial({
  uniforms,
  transparent: true,
  depthWrite: false,
  vertexShader: `
    uniform float uTime, uSize;
    uniform vec3 uMouse;
    varying float vMix, vH, vD;
    void main() {
      vec3 p = position;
      float h = sin(p.x * .22 + uTime * .6) * .8
              + cos(p.z * .3 + uTime * .45) * .6
              + sin((p.x + p.z) * .12 + uTime * .3) * .5;
      float d = distance(p.xz, uMouse.xz);
      h += sin(d * 1.1 - uTime * 3.) * exp(-d * .09) * 1.6; // rings spreading out from the mouse
      vD = d;
      p.y += h;
      vH = h;
      vMix = position.x / ${W.toFixed(1)} + .5;
      vec4 mv = modelViewMatrix * vec4(p, 1.);
      gl_PointSize = uSize * (18. / -mv.z);
      gl_Position = projectionMatrix * mv;
    }`,
  fragmentShader: `
    uniform vec3 uA1, uA2, uA3;
    uniform float uAlpha, uTime;
    varying float vMix, vH, vD;
    void main() {
      float r = length(gl_PointCoord - .5);
      if (r > .5) discard;
      vec3 c = vMix < .55 ? mix(uA1, uA2, vMix / .55) : mix(uA2, uA3, (vMix - .55) / .45);
      vec3 rainbow = .5 + .5 * cos(6.2832 * (vD * .06 - uTime * .25 + vec3(0., .33, .67))); // hue shifts ring by ring
      c = mix(c, rainbow, exp(-vD * .07));
      gl_FragColor = vec4(c, uAlpha * (1. - r * 2.) * clamp(.45 + .25 * vH, .15, 1.));
    }`,
});
scene.add(new THREE.Points(geo, mat));

function syncTheme() {
  const css = getComputedStyle(root);
  ['uA1', 'uA2', 'uA3'].forEach((u, i) => uniforms[u].value.setStyle(css.getPropertyValue(`--a${i + 1}`).trim()));
  const dark = css.getPropertyValue('color-scheme').trim() !== 'light';
  mat.blending = dark ? THREE.AdditiveBlending : THREE.NormalBlending;
  uniforms.uAlpha.value = dark ? 0.8 : 1;
  mat.needsUpdate = true;
}
syncTheme();
new MutationObserver(syncTheme).observe(root, { attributes: true, attributeFilter: ['data-theme', 'data-accent'] });
matchMedia('(prefers-color-scheme: light)').addEventListener('change', syncTheme);

function resize() {
  renderer.setSize(innerWidth, innerHeight, false);
  camera.aspect = innerWidth / innerHeight;
  camera.updateProjectionMatrix();
}
resize();
addEventListener('resize', resize);

// mouse → point on the y=0 plane, eased so the ripple glides
const ray = new THREE.Raycaster(), ndc = new THREE.Vector2(), hit = new THREE.Vector3();
const ground = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
let target = null;
addEventListener('pointermove', e => {
  ndc.set(e.clientX / innerWidth * 2 - 1, -(e.clientY / innerHeight) * 2 + 1);
  ray.setFromCamera(ndc, camera);
  target = ray.ray.intersectPlane(ground, hit) ? hit.clone() : null;
}, { passive: true });

// camera eases toward a pose per section (main.js sets body[data-section])
const cam = { x: 0, y: 7, z: 14 }, look = new THREE.Vector3(0, 0, -6);
const clock = new THREE.Clock();
function frame() {
  const t = clock.getElapsedTime(), s = +(document.body.dataset.section || 0);
  uniforms.uTime.value = t;
  cam.x += (Math.sin(s * 1.3) * 6 + Math.sin(t * .1) * 1.5 - cam.x) * .03;
  cam.y += (7 - s * .5 - cam.y) * .03;
  cam.z += (14 - s * 1.2 - cam.z) * .03;
  camera.position.set(cam.x, cam.y, cam.z);
  camera.lookAt(look);
  if (target) uniforms.uMouse.value.lerp(target, .12);
  renderer.render(scene, camera);
  if (!reduced) requestAnimationFrame(frame);
}
frame();
