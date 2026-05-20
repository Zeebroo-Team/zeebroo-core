#pragma once

#include <QWidget>

class QAction;
class QHBoxLayout;
class QStackedWidget;
class QTabBar;
class QToolButton;
class QWidget;

/** Word-style contextual ribbon — flat tabs / blue underline, grouped panels. */
class RibbonWidget final : public QWidget
{
    Q_OBJECT

public:
    explicit RibbonWidget(QWidget* parent = nullptr);

private:
    QWidget* buildHomePage();
    QWidget* buildInsertPage();
    QWidget* buildDrawPage();
    QWidget* buildLayoutPage();

    QWidget* buildFontBlock(QWidget* row);
    QWidget* buildParagraphBlock(QWidget* row);
    QWidget* buildStylesBlock(QWidget* row);
    QWidget* buildVoiceBlock(QWidget* row);

    static QToolButton* makeRibbonButton(QAction* action);
    static QToolButton* makeSmallIconButton(const QIcon& icon, const QString& tip, QWidget* parent);
    static QToolButton* makeGlyphButton(const QString& glyph, const QString& tip, QWidget* parent);

    QTabBar* m_tabs{};
    QStackedWidget* m_stack{};
};
