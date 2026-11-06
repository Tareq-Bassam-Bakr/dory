import 'package:material_ui/material_ui.dart';
import 'package:qr/qr.dart';

/// A QR code for [data], drawn with dark modules on a white quiet zone so
/// phone cameras read it in light and dark mode alike.
class QueueQrCode extends StatelessWidget {
  const QueueQrCode({
    super.key,
    required this.data,
    required this.semanticLabel,
    this.size = 240,
  });

  final String data;
  final String semanticLabel;
  final double size;

  @override
  Widget build(BuildContext context) {
    final QrImage image = QrImage(
      QrCode(
        payload: QrPayload.fromString(data),
        errorCorrectLevel: QrErrorCorrectLevel.medium,
      ),
    );
    return Semantics(
      label: semanticLabel,
      image: true,
      child: SizedBox.square(
        dimension: size,
        child: DecoratedBox(
          decoration: const BoxDecoration(color: Color(0xFFFFFFFF)),
          child: CustomPaint(painter: _QrPainter(image)),
        ),
      ),
    );
  }
}

class _QrPainter extends CustomPainter {
  _QrPainter(this.image);

  final QrImage image;

  /// Quiet zone around the code, in modules (the standard asks for 4).
  static const int _quiet = 4;

  @override
  void paint(Canvas canvas, Size size) {
    final int count = image.moduleCount + 2 * _quiet;
    final double module = size.shortestSide / count;
    final Paint dark = Paint()
      ..color = const Color(0xFF000000)
      ..isAntiAlias = false;

    for (int row = 0; row < image.moduleCount; row++) {
      for (int col = 0; col < image.moduleCount; col++) {
        if (image.isDark(row, col)) {
          canvas.drawRect(
            Rect.fromLTWH(
              (col + _quiet) * module,
              (row + _quiet) * module,
              module + 0.5,
              module + 0.5,
            ),
            dark,
          );
        }
      }
    }
  }

  @override
  bool shouldRepaint(_QrPainter oldDelegate) => oldDelegate.image != image;
}
