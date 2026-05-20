#include "MainWindow.h"

#include <QApplication>
#include <QtGlobal>
#if defined(Q_OS_MACOS)
#include <QString>
#endif

int main(int argc, char* argv[])
{
    QApplication app(argc, argv);

    // Native "macintosh" ignores much of Qt Style Sheets; Fusion matches Office-like chrome better.
#if defined(Q_OS_MACOS)
    app.setStyle(QStringLiteral("Fusion"));
#endif

    QApplication::setApplicationName(QStringLiteral("Zeebroo Desktop"));
    QApplication::setOrganizationName(QStringLiteral("Zeebroo"));

    MainWindow window;
    window.resize(1180, 720);
    window.show();

    return app.exec();
}
