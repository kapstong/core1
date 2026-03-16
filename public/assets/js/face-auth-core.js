(function(global) {
    'use strict';

    if (global.FaceAuthCore) return;

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function percentile(sortedValues, p) {
        if (!Array.isArray(sortedValues) || !sortedValues.length) return 0;
        const idx = Math.floor((sortedValues.length - 1) * clamp(p, 0, 1));
        return Number(sortedValues[idx]) || 0;
    }

    function distance2D(p1, p2) {
        const dx = (p1.x || 0) - (p2.x || 0);
        const dy = (p1.y || 0) - (p2.y || 0);
        return Math.sqrt((dx * dx) + (dy * dy));
    }

    function averagePoint(points) {
        if (!Array.isArray(points) || !points.length) return { x: 0, y: 0 };
        let sumX = 0;
        let sumY = 0;
        for (let i = 0; i < points.length; i++) {
            sumX += Number(points[i].x) || 0;
            sumY += Number(points[i].y) || 0;
        }
        return {
            x: sumX / points.length,
            y: sumY / points.length
        };
    }

    function eyeAspectRatio(eyePoints) {
        if (!Array.isArray(eyePoints) || eyePoints.length < 6) return 1;
        const verticalA = distance2D(eyePoints[1], eyePoints[5]);
        const verticalB = distance2D(eyePoints[2], eyePoints[4]);
        const horizontal = distance2D(eyePoints[0], eyePoints[3]);
        if (horizontal === 0) return 1;
        return (verticalA + verticalB) / (2 * horizontal);
    }

    function eyeAperture(eyePoints, faceHeight) {
        if (!Array.isArray(eyePoints) || eyePoints.length < 6) return 0;
        const verticalA = distance2D(eyePoints[1], eyePoints[5]);
        const verticalB = distance2D(eyePoints[2], eyePoints[4]);
        const avgVertical = (verticalA + verticalB) / 2;
        return avgVertical / Math.max(1, Number(faceHeight) || 1);
    }

    function normalizeDescriptor(descriptor) {
        if (!Array.isArray(descriptor) || descriptor.length !== 128) return null;
        let sumSquares = 0;
        for (let i = 0; i < descriptor.length; i++) {
            const value = Number(descriptor[i]) || 0;
            sumSquares += value * value;
        }
        const magnitude = Math.sqrt(sumSquares) || 1;
        return descriptor.map(function(value) {
            return (Number(value) || 0) / magnitude;
        });
    }

    function averageDescriptors(descriptors) {
        if (!Array.isArray(descriptors) || !descriptors.length) return null;
        const sum = new Array(128).fill(0);
        let count = 0;

        for (let i = 0; i < descriptors.length; i++) {
            const descriptor = descriptors[i];
            if (!Array.isArray(descriptor) || descriptor.length !== 128) continue;
            for (let j = 0; j < 128; j++) {
                sum[j] += Number(descriptor[j]) || 0;
            }
            count++;
        }

        if (!count) return null;
        return normalizeDescriptor(sum.map(function(value) {
            return value / count;
        }));
    }

    function createDescriptorAverager(maxSamples) {
        const limit = Math.max(6, Number(maxSamples) || 18);
        const state = {
            samples: [],
            sum: new Array(128).fill(0),
            averaged: null
        };

        function rebuildAverage() {
            if (!state.samples.length) {
                state.averaged = null;
                return null;
            }

            const count = state.samples.length;
            const average = new Array(128);
            for (let i = 0; i < 128; i++) {
                average[i] = state.sum[i] / count;
            }
            state.averaged = normalizeDescriptor(average);
            return state.averaged;
        }

        return {
            add: function(descriptor) {
                const normalized = normalizeDescriptor(descriptor);
                if (!normalized) return state.averaged;

                state.samples.push(normalized);
                for (let i = 0; i < 128; i++) {
                    state.sum[i] += normalized[i];
                }

                if (state.samples.length > limit) {
                    const removed = state.samples.shift();
                    for (let i = 0; i < 128; i++) {
                        state.sum[i] -= removed[i];
                    }
                }

                return rebuildAverage();
            },
            reset: function() {
                state.samples = [];
                state.sum = new Array(128).fill(0);
                state.averaged = null;
            },
            getDescriptor: function() {
                return state.averaged;
            },
            getSampleCount: function() {
                return state.samples.length;
            },
            getSamples: function() {
                return state.samples.slice();
            }
        };
    }

    function createBlinkEngine(options) {
        const cfg = Object.assign({
            historySize: 42,
            calibrationFrames: 28,
            cooldownMs: 850,
            phaseTimeoutMs: 1700,
            nearProximityThreshold: 0.42
        }, options || {});

        const state = {};

        function reset() {
            state.blinkCount = 0;
            state.livenessAt = null;
            state.phase = 'wait_close';
            state.phaseAt = 0;
            state.closeStreak = 0;
            state.openStreak = 0;
            state.signalHistory = [];
            state.apertureHistory = [];
            state.signalSmooth = 0;
            state.apertureSmooth = 0;
            state.signalPeak = 0;
            state.aperturePeak = 0;
            state.signalOpenBase = 0;
            state.apertureOpenBase = 0;
            state.signalCloseThreshold = 0.12;
            state.signalOpenThreshold = 0.165;
            state.apertureCloseThreshold = 0.009;
            state.apertureOpenThreshold = 0.0145;
            state.signalRollMax = 0;
            state.apertureRollMax = 0;
            state.signalRollDrop = 0;
            state.apertureRollDrop = 0;
            state.lastSignal = 0;
            state.lastAperture = 0;
            state.cooldownUntil = 0;
            state.signalDropPeak = 0;
            state.apertureDropPeak = 0;
            state.calibrationFrames = 0;
        }

        function update(params) {
            if (!params || !params.landmarks || !params.box) return null;

            const now = Number(params.now) || Date.now();
            const leftEye = params.landmarks.getLeftEye ? params.landmarks.getLeftEye() : null;
            const rightEye = params.landmarks.getRightEye ? params.landmarks.getRightEye() : null;
            if (!leftEye || !rightEye) return null;

            const faceWidth = Math.max(1, Number(params.box.width) || 1);
            const faceHeight = Math.max(1, Number(params.box.height) || 1);
            const frameWidth = Math.max(1, Number(params.frameWidth) || 1);
            const frameHeight = Math.max(1, Number(params.frameHeight) || 1);

            const leftEAR = eyeAspectRatio(leftEye);
            const rightEAR = eyeAspectRatio(rightEye);
            const earAverage = (leftEAR + rightEAR) / 2;
            const dominantEye = Math.min(leftEAR, rightEAR);
            const signalRaw = (earAverage * 0.58) + (dominantEye * 0.42);

            const leftAperture = eyeAperture(leftEye, faceHeight);
            const rightAperture = eyeAperture(rightEye, faceHeight);
            const apertureRaw = (leftAperture + rightAperture) / 2;

            const faceArea = faceWidth * faceHeight;
            const frameArea = frameWidth * frameHeight;
            const proximity = clamp(faceArea / Math.max(1, frameArea), 0, 1);
            const nearMode = proximity >= cfg.nearProximityThreshold;

            if (signalRaw > 0.05 && signalRaw < 0.5) {
                state.signalHistory.push(signalRaw);
                if (state.signalHistory.length > cfg.historySize) state.signalHistory.shift();
                state.signalPeak = Math.max(signalRaw, state.signalPeak * 0.995);
                state.signalSmooth = state.signalSmooth
                    ? (state.signalSmooth * 0.42) + (signalRaw * 0.58)
                    : signalRaw;
            }

            if (apertureRaw > 0.003 && apertureRaw < 0.2) {
                state.apertureHistory.push(apertureRaw);
                if (state.apertureHistory.length > cfg.historySize) state.apertureHistory.shift();
                state.aperturePeak = Math.max(apertureRaw, state.aperturePeak * 0.996);
                state.apertureSmooth = state.apertureSmooth
                    ? (state.apertureSmooth * 0.44) + (apertureRaw * 0.56)
                    : apertureRaw;
            }

            const signal = state.signalSmooth || signalRaw;
            const aperture = state.apertureSmooth || apertureRaw;

            if (state.signalHistory.length >= 6) {
                const sortedSignal = state.signalHistory.slice().sort(function(a, b) { return a - b; });
                const p90 = percentile(sortedSignal, 0.9);
                const p20 = percentile(sortedSignal, 0.2);
                const openBase = Math.max(p90 || 0.18, state.signalPeak || 0.18);
                const signalRange = Math.max(0.012, openBase - (p20 || (openBase - 0.012)));
                const closeFactor = nearMode ? 0.5 : 0.62;
                const openFactor = nearMode ? 0.16 : 0.2;

                state.signalCloseThreshold = clamp(openBase - (signalRange * closeFactor), 0.072, 0.24);
                state.signalOpenThreshold = clamp(
                    openBase - (signalRange * openFactor),
                    state.signalCloseThreshold + 0.013,
                    0.34
                );

                if (state.calibrationFrames < cfg.calibrationFrames) {
                    state.signalOpenBase = state.signalOpenBase
                        ? (state.signalOpenBase * 0.91) + (openBase * 0.09)
                        : openBase;
                }
            }

            if (state.apertureHistory.length >= 6) {
                const sortedAperture = state.apertureHistory.slice().sort(function(a, b) { return a - b; });
                const p90a = percentile(sortedAperture, 0.9);
                const p20a = percentile(sortedAperture, 0.2);
                const openA = Math.max(p90a || 0.018, state.aperturePeak || 0.018);
                const apertureRange = Math.max(0.0026, openA - (p20a || (openA - 0.0026)));
                const closeAFactor = nearMode ? 0.74 : 0.66;
                const openAFactor = nearMode ? 0.3 : 0.24;

                state.apertureCloseThreshold = clamp(openA - (apertureRange * closeAFactor), 0.0058, 0.038);
                state.apertureOpenThreshold = clamp(
                    openA - (apertureRange * openAFactor),
                    state.apertureCloseThreshold + 0.0014,
                    0.06
                );

                if (state.calibrationFrames < cfg.calibrationFrames) {
                    state.apertureOpenBase = state.apertureOpenBase
                        ? (state.apertureOpenBase * 0.9) + (openA * 0.1)
                        : openA;
                }
            }

            if (state.calibrationFrames < cfg.calibrationFrames) {
                state.calibrationFrames++;
            }

            state.signalRollMax = Math.max(signal, state.signalRollMax * 0.993);
            state.apertureRollMax = Math.max(aperture, state.apertureRollMax * 0.992);

            const signalBase = Math.max(state.signalOpenBase || 0, state.signalRollMax || 0, state.signalOpenThreshold + 0.011);
            const apertureBase = Math.max(state.apertureOpenBase || 0, state.apertureRollMax || 0, state.apertureOpenThreshold + 0.0012);

            const signalDrop = signalBase > 0 ? clamp((signalBase - signal) / signalBase, 0, 1) : 0;
            const apertureDrop = apertureBase > 0 ? clamp((apertureBase - aperture) / apertureBase, 0, 1) : 0;

            state.signalRollDrop = (state.signalRollDrop * 0.72) + (signalDrop * 0.28);
            state.apertureRollDrop = (state.apertureRollDrop * 0.7) + (apertureDrop * 0.3);

            const signalDelta = signal - (state.lastSignal || signal);
            const apertureDelta = aperture - (state.lastAperture || aperture);
            state.lastSignal = signal;
            state.lastAperture = aperture;

            const signalCloseDropTrigger = nearMode ? 0.075 : 0.1;
            const apertureCloseDropTrigger = nearMode ? 0.1 : 0.145;

            const shouldClose = (
                signal <= state.signalCloseThreshold
                || aperture <= state.apertureCloseThreshold
                || state.signalRollDrop >= signalCloseDropTrigger
                || state.apertureRollDrop >= apertureCloseDropTrigger
                || (signalDelta <= -0.014 && apertureDelta <= -0.001)
            );

            const shouldOpen = (
                signal >= state.signalOpenThreshold
                || aperture >= state.apertureOpenThreshold
                || (state.signalRollDrop <= 0.055 && state.apertureRollDrop <= 0.075)
                || (signalDelta >= 0.011 && apertureDelta >= 0.0009)
            );

            let blinkDetected = false;

            if (state.phase === 'wait_close') {
                if (shouldClose) {
                    state.closeStreak++;
                    if (state.closeStreak >= 1) {
                        state.phase = 'wait_open';
                        state.phaseAt = now;
                        state.openStreak = 0;
                        state.signalDropPeak = state.signalRollDrop;
                        state.apertureDropPeak = state.apertureRollDrop;
                    }
                } else {
                    state.closeStreak = 0;
                }
            } else {
                state.signalDropPeak = Math.max(state.signalDropPeak, state.signalRollDrop);
                state.apertureDropPeak = Math.max(state.apertureDropPeak, state.apertureRollDrop);

                if (shouldOpen) {
                    state.openStreak++;
                    if (state.openStreak >= 1) {
                        const meaningfulClose = (
                            state.signalDropPeak >= (nearMode ? 0.055 : 0.08)
                            || state.apertureDropPeak >= (nearMode ? 0.1 : 0.13)
                            || signal <= (state.signalCloseThreshold + 0.01)
                            || aperture <= (state.apertureCloseThreshold + 0.001)
                        );

                        if (meaningfulClose && now > state.cooldownUntil) {
                            state.blinkCount++;
                            state.cooldownUntil = now + cfg.cooldownMs;
                            blinkDetected = true;
                            if (!state.livenessAt) state.livenessAt = new Date().toISOString();
                        }

                        state.phase = 'wait_close';
                        state.phaseAt = 0;
                        state.closeStreak = 0;
                        state.openStreak = 0;
                        state.signalDropPeak = 0;
                        state.apertureDropPeak = 0;
                    }
                } else if (shouldClose) {
                    state.openStreak = 0;
                }

                if (state.phaseAt > 0 && (now - state.phaseAt) > cfg.phaseTimeoutMs) {
                    state.phase = 'wait_close';
                    state.phaseAt = 0;
                    state.closeStreak = 0;
                    state.openStreak = 0;
                    state.signalDropPeak = 0;
                    state.apertureDropPeak = 0;
                }
            }

            return {
                leftEAR: leftEAR,
                rightEAR: rightEAR,
                signal: signal,
                aperture: aperture,
                signalCloseThreshold: state.signalCloseThreshold,
                signalOpenThreshold: state.signalOpenThreshold,
                apertureCloseThreshold: state.apertureCloseThreshold,
                apertureOpenThreshold: state.apertureOpenThreshold,
                signalDrop: state.signalRollDrop,
                apertureDrop: state.apertureRollDrop,
                blinkCount: state.blinkCount,
                livenessAt: state.livenessAt,
                phase: state.phase,
                blinkDetected: blinkDetected,
                proximity: proximity,
                nearMode: nearMode,
                calibrationPct: Math.round((state.calibrationFrames / cfg.calibrationFrames) * 100)
            };
        }

        reset();
        return {
            state: state,
            reset: reset,
            update: update
        };
    }

    function getClientHardwareInfo() {
        const nav = global.navigator || {};
        const width = Number(global.innerWidth) || 0;
        const height = Number(global.innerHeight) || 0;
        const smallestViewportSide = Math.min(width || Number.MAX_SAFE_INTEGER, height || Number.MAX_SAFE_INTEGER);

        return {
            hardwareConcurrency: Math.max(0, Number(nav.hardwareConcurrency) || 0),
            deviceMemory: Math.max(0, Number(nav.deviceMemory) || 0),
            prefersReducedMotion: !!(global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches),
            smallestViewportSide: Number.isFinite(smallestViewportSide) ? smallestViewportSide : 0
        };
    }

    function createScannerConfig(options) {
        const opts = Object.assign({
            preferLowPower: false,
            detailLevel: null,
            maxSamples: null
        }, options || {});
        const hardware = getClientHardwareInfo();
        const lowEnd = !!(
            opts.preferLowPower
            || hardware.prefersReducedMotion
            || (hardware.deviceMemory > 0 && hardware.deviceMemory <= 4)
            || (hardware.hardwareConcurrency > 0 && hardware.hardwareConcurrency <= 4)
        );
        const strongHardware = !lowEnd && !!(
            (hardware.smallestViewportSide === 0 || hardware.smallestViewportSide > 900)
            && (
                hardware.deviceMemory > 8
                || hardware.hardwareConcurrency > 8
            )
        );
        const balanced = !lowEnd && !strongHardware;

        const base = lowEnd
            ? {
                cameraWidth: 640,
                cameraHeight: 480,
                cameraFrameRateIdeal: 12,
                cameraFrameRateMax: 15,
                detectorInputSize: 160,
                detectorScoreThreshold: 0.16,
                detectIntervalMs: 135,
                idleDetectIntervalMs: 220,
                descriptorIntervalMs: 420,
                maxDescriptorSamples: 10,
                overlayDetail: 'minimal',
                useTinyLandmarkNet: true,
                trackGraceMs: 1800
            }
            : balanced
                ? {
                    cameraWidth: 640,
                    cameraHeight: 480,
                    cameraFrameRateIdeal: 15,
                    cameraFrameRateMax: 18,
                    detectorInputSize: 224,
                    detectorScoreThreshold: 0.15,
                    detectIntervalMs: 105,
                    idleDetectIntervalMs: 180,
                    descriptorIntervalMs: 320,
                    maxDescriptorSamples: 12,
                    overlayDetail: 'full',
                    useTinyLandmarkNet: true,
                    trackGraceMs: 1800
                }
                : {
                    cameraWidth: 768,
                    cameraHeight: 576,
                    cameraFrameRateIdeal: 18,
                    cameraFrameRateMax: 20,
                    detectorInputSize: 224,
                    detectorScoreThreshold: 0.14,
                    detectIntervalMs: 90,
                    idleDetectIntervalMs: 150,
                    descriptorIntervalMs: 260,
                    maxDescriptorSamples: 14,
                    overlayDetail: 'full',
                    useTinyLandmarkNet: true,
                    trackGraceMs: 1800
                };

        return Object.assign({
            tier: lowEnd ? 'low' : (strongHardware ? 'high' : 'balanced'),
            hardware: hardware
        }, base, {
            overlayDetail: opts.detailLevel || base.overlayDetail,
            maxDescriptorSamples: Math.max(6, Number(opts.maxSamples) || base.maxDescriptorSamples)
        });
    }

    function getVideoConstraints(config) {
        const profile = config || createScannerConfig();
        return {
            facingMode: 'user',
            width: { ideal: profile.cameraWidth },
            height: { ideal: profile.cameraHeight },
            frameRate: {
                ideal: profile.cameraFrameRateIdeal,
                max: profile.cameraFrameRateMax
            }
        };
    }

    function createTinyFaceDetectorOptions(config) {
        const profile = config || createScannerConfig();
        if (!global.faceapi || typeof global.faceapi.TinyFaceDetectorOptions !== 'function') {
            return null;
        }

        return new global.faceapi.TinyFaceDetectorOptions({
            inputSize: profile.detectorInputSize,
            scoreThreshold: profile.detectorScoreThreshold
        });
    }

    function ensureCanvasSize(canvas, video) {
        if (!canvas || !video) return;
        const width = video.clientWidth || 0;
        const height = video.clientHeight || 0;
        if (!width || !height) return;
        if (canvas.width !== width) canvas.width = width;
        if (canvas.height !== height) canvas.height = height;
    }

    function clearOverlay(ctx, canvas) {
        if (!ctx || !canvas) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function drawPolyline(ctx, points, color, width, closed, alpha) {
        if (!ctx || !Array.isArray(points) || points.length < 2) return;
        ctx.beginPath();
        ctx.globalAlpha = alpha == null ? 1 : alpha;
        ctx.strokeStyle = color;
        ctx.lineWidth = width == null ? 2 : width;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.moveTo(points[0].x, points[0].y);
        for (let i = 1; i < points.length; i++) {
            ctx.lineTo(points[i].x, points[i].y);
        }
        if (closed) ctx.closePath();
        ctx.stroke();
        ctx.globalAlpha = 1;
    }

    function drawGlowPolyline(ctx, points, color, width, closed) {
        if (!ctx || !Array.isArray(points) || points.length < 2) return;
        ctx.save();
        ctx.shadowColor = color;
        ctx.shadowBlur = 12;
        drawPolyline(ctx, points, color, (width || 2) + 0.7, closed, 0.45);
        ctx.restore();
        drawPolyline(ctx, points, color, width || 2, closed, 0.95);
    }

    function drawCornerBrackets(ctx, box, color) {
        if (!ctx || !box) return;
        const length = Math.min(28, Math.max(14, box.width * 0.11));
        ctx.strokeStyle = color;
        ctx.lineWidth = 2.5;
        ctx.beginPath();
        ctx.moveTo(box.x, box.y + length);
        ctx.lineTo(box.x, box.y);
        ctx.lineTo(box.x + length, box.y);
        ctx.moveTo(box.x + box.width - length, box.y);
        ctx.lineTo(box.x + box.width, box.y);
        ctx.lineTo(box.x + box.width, box.y + length);
        ctx.moveTo(box.x + box.width, box.y + box.height - length);
        ctx.lineTo(box.x + box.width, box.y + box.height);
        ctx.lineTo(box.x + box.width - length, box.y + box.height);
        ctx.moveTo(box.x + length, box.y + box.height);
        ctx.lineTo(box.x, box.y + box.height);
        ctx.lineTo(box.x, box.y + box.height - length);
        ctx.stroke();
    }

    function drawNode(ctx, point, radius, color) {
        if (!ctx || !point) return;
        ctx.save();
        ctx.shadowColor = color;
        ctx.shadowBlur = 12;
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(point.x, point.y, radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    function drawEyeMeter(ctx, eyePoints, value, closeThreshold, openThreshold) {
        if (!ctx || !Array.isArray(eyePoints) || !eyePoints.length) return;
        const center = averagePoint(eyePoints);
        const topY = Math.min.apply(null, eyePoints.map(function(p) { return p.y; })) - 14;
        const meterWidth = 52;
        const meterHeight = 5;
        const normalized = clamp(
            (value - closeThreshold) / Math.max(0.0001, openThreshold - closeThreshold),
            0,
            1
        );

        ctx.fillStyle = 'rgba(8, 18, 35, 0.72)';
        ctx.fillRect(center.x - meterWidth / 2, topY, meterWidth, meterHeight);

        const meterGradient = ctx.createLinearGradient(center.x - meterWidth / 2, topY, center.x + meterWidth / 2, topY);
        meterGradient.addColorStop(0, '#ff6f7f');
        meterGradient.addColorStop(0.55, '#ffd66d');
        meterGradient.addColorStop(1, '#49ffaa');
        ctx.fillStyle = meterGradient;
        ctx.fillRect(center.x - meterWidth / 2, topY, meterWidth * normalized, meterHeight);

        ctx.strokeStyle = 'rgba(168, 240, 255, 0.45)';
        ctx.lineWidth = 1;
        ctx.strokeRect(center.x - meterWidth / 2, topY, meterWidth, meterHeight);
    }

    function createOverlayState() {
        return {
            scanPhase: 0,
            pulsePhase: 0
        };
    }

    function drawScannerOverlay(params) {
        if (!params || !params.ctx || !params.canvas || !params.video || !params.detection) return null;

        const ctx = params.ctx;
        const canvas = params.canvas;
        const video = params.video;
        const detection = params.detection;
        const metrics = params.metrics || {};
        const overlayState = params.overlayState || createOverlayState();
        const detailLevel = params.detailLevel || 'full';
        const minimalDetail = detailLevel === 'minimal';

        ensureCanvasSize(canvas, video);
        const width = canvas.width;
        const height = canvas.height;
        if (!width || !height) return null;

        ctx.clearRect(0, 0, width, height);

        let resized = detection;
        if (global.faceapi && typeof global.faceapi.resizeResults === 'function') {
            resized = global.faceapi.resizeResults(detection, { width: width, height: height });
        }

        const landmarks = resized.landmarks;
        const box = resized.detection ? resized.detection.box : resized.box;
        if (!landmarks || !box) return null;

        const leftEye = landmarks.getLeftEye ? landmarks.getLeftEye() : [];
        const rightEye = landmarks.getRightEye ? landmarks.getRightEye() : [];
        const mouth = landmarks.getMouth ? landmarks.getMouth() : [];
        const nose = landmarks.getNose ? landmarks.getNose() : [];
        const jaw = landmarks.getJawOutline ? landmarks.getJawOutline() : [];
        const leftBrow = landmarks.getLeftEyeBrow ? landmarks.getLeftEyeBrow() : [];
        const rightBrow = landmarks.getRightEyeBrow ? landmarks.getRightEyeBrow() : [];

        const leftEyeCenter = averagePoint(leftEye);
        const rightEyeCenter = averagePoint(rightEye);
        const noseTip = nose.length ? nose[nose.length - 1] : averagePoint(nose);
        const mouthCenter = averagePoint(mouth);

        overlayState.pulsePhase += 0.12;
        overlayState.scanPhase += 0.2;

        const pulse = 0.45 + (0.55 * ((Math.sin(overlayState.pulsePhase) + 1) * 0.5));

        const frameStroke = minimalDetail
            ? 'rgba(92, 239, 255, 0.95)'
            : ctx.createLinearGradient(box.x, box.y, box.x + box.width, box.y + box.height);
        if (!minimalDetail) {
            frameStroke.addColorStop(0, 'rgba(76, 234, 255, 0.96)');
            frameStroke.addColorStop(1, 'rgba(98, 255, 194, 0.95)');
        }
        ctx.strokeStyle = frameStroke;
        ctx.lineWidth = 1.8;
        ctx.strokeRect(box.x, box.y, box.width, box.height);
        drawCornerBrackets(ctx, box, 'rgba(115, 255, 231, 0.95)');

        if (!minimalDetail) {
            const haloGradient = ctx.createRadialGradient(
                box.x + (box.width / 2),
                box.y + (box.height / 2),
                box.width * 0.1,
                box.x + (box.width / 2),
                box.y + (box.height / 2),
                box.width * 0.75
            );
            haloGradient.addColorStop(0, 'rgba(70, 231, 255, 0.2)');
            haloGradient.addColorStop(1, 'rgba(70, 231, 255, 0)');
            ctx.fillStyle = haloGradient;
            ctx.fillRect(box.x, box.y, box.width, box.height);

            ctx.strokeStyle = 'rgba(140, 233, 255, 0.12)';
            ctx.lineWidth = 1;
            for (let i = 1; i < 4; i++) {
                const x = box.x + ((box.width * i) / 4);
                const y = box.y + ((box.height * i) / 4);
                ctx.beginPath();
                ctx.moveTo(x, box.y);
                ctx.lineTo(x, box.y + box.height);
                ctx.moveTo(box.x, y);
                ctx.lineTo(box.x + box.width, y);
                ctx.stroke();
            }
        }

        if (minimalDetail) {
            drawPolyline(ctx, leftEye, '#00e5ff', 1.7, true, 0.95);
            drawPolyline(ctx, rightEye, '#00e5ff', 1.7, true, 0.95);
            drawPolyline(ctx, nose, '#49ffba', 1.6, false, 0.9);
            drawPolyline(ctx, mouth, '#ffd36d', 1.5, true, 0.85);
        } else {
            drawGlowPolyline(ctx, leftEye, '#00e5ff', 2.2, true);
            drawGlowPolyline(ctx, rightEye, '#00e5ff', 2.2, true);
            drawGlowPolyline(ctx, nose, '#49ffba', 2.1, false);
            drawGlowPolyline(ctx, mouth, '#ffd36d', 2.1, true);
            drawPolyline(ctx, jaw, 'rgba(235, 248, 255, 0.43)', 1.45, false, 1);
            drawPolyline(ctx, leftBrow, 'rgba(98, 228, 255, 0.9)', 1.7, false, 1);
            drawPolyline(ctx, rightBrow, 'rgba(98, 228, 255, 0.9)', 1.7, false, 1);

            drawPolyline(ctx, [leftEyeCenter, noseTip, rightEyeCenter], 'rgba(118,255,219,0.85)', 1.7, false, 0.9);
            drawPolyline(ctx, [leftEyeCenter, mouthCenter, rightEyeCenter], 'rgba(250,210,127,0.72)', 1.2, false, 0.85);

            ctx.save();
            ctx.setLineDash([7, 5]);
            ctx.strokeStyle = 'rgba(112, 240, 255, 0.34)';
            ctx.lineWidth = 1.4;
            ctx.beginPath();
            ctx.ellipse(
                box.x + (box.width / 2),
                box.y + (box.height / 2),
                box.width * 0.48,
                box.height * 0.46,
                0,
                0,
                Math.PI * 2
            );
            ctx.stroke();
            ctx.restore();

            drawNode(ctx, leftEyeCenter, 2.4 + (pulse * 0.9), 'rgba(110, 255, 245, 0.95)');
            drawNode(ctx, rightEyeCenter, 2.4 + (pulse * 0.9), 'rgba(110, 255, 245, 0.95)');
            drawNode(ctx, noseTip, 2.1 + (pulse * 0.7), 'rgba(255, 230, 176, 0.95)');

            const scanY = box.y + (((Math.sin(overlayState.scanPhase) + 1) * 0.5) * box.height);
            const scanGradient = ctx.createLinearGradient(box.x, scanY, box.x + box.width, scanY);
            scanGradient.addColorStop(0, 'rgba(120, 255, 187, 0)');
            scanGradient.addColorStop(0.2, 'rgba(120, 255, 187, 0.86)');
            scanGradient.addColorStop(0.8, 'rgba(120, 255, 187, 0.86)');
            scanGradient.addColorStop(1, 'rgba(120, 255, 187, 0)');
            ctx.beginPath();
            ctx.strokeStyle = scanGradient;
            ctx.lineWidth = 2.2;
            ctx.moveTo(box.x, scanY);
            ctx.lineTo(box.x + box.width, scanY);
            ctx.stroke();
        }

        const signal = Number(metrics.signal) || 0;
        const closeThreshold = Number(metrics.signalCloseThreshold) || 0.12;
        const openThreshold = Number(metrics.signalOpenThreshold) || 0.18;
        drawEyeMeter(ctx, leftEye, signal, closeThreshold, openThreshold);
        drawEyeMeter(ctx, rightEye, signal, closeThreshold, openThreshold);

        return {
            box: box,
            landmarks: landmarks,
            overlayState: overlayState
        };
    }

    global.FaceAuthCore = {
        clamp: clamp,
        distance2D: distance2D,
        eyeAspectRatio: eyeAspectRatio,
        normalizeDescriptor: normalizeDescriptor,
        averageDescriptors: averageDescriptors,
        createDescriptorAverager: createDescriptorAverager,
        createBlinkEngine: createBlinkEngine,
        createScannerConfig: createScannerConfig,
        getVideoConstraints: getVideoConstraints,
        createTinyFaceDetectorOptions: createTinyFaceDetectorOptions,
        ensureCanvasSize: ensureCanvasSize,
        clearOverlay: clearOverlay,
        createOverlayState: createOverlayState,
        drawScannerOverlay: drawScannerOverlay
    };
})(window);
