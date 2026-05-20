#pragma once

#include <QMainWindow>

class QLabel;
class RibbonWidget;
class QTextEdit;

class MainWindow final : public QMainWindow
{
    Q_OBJECT

public:
    explicit MainWindow(QWidget* parent = nullptr);

private:
    void applyOfficeLikeChrome();

    QLabel* m_docTitle{};
    QTextEdit* m_editor{};
    RibbonWidget* m_ribbon{};
};
