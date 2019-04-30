package app;

import java.util.TreeMap;

import commonui.FormConfirmBox;
import core.Controller;
import templatebuilder.TemplateBuilder;
import virtualui.*;

public class FormFirstPage extends VirtualUIForm implements FrameFirstPageListener {
	
	TemplateBuilder m_oTemplateBuilder;
	
	private FrameFirstPage m_oFrameFirstPage;
	
	public FormFirstPage(Controller oParentController){
		super(oParentController);
		
		m_oTemplateBuilder = new TemplateBuilder();
		
		// Load form from template file
		m_oTemplateBuilder.loadTemplate("frmFirstPage.xml");
		
		// Background Cover Page
		VirtualUIFrame oCoverFrame = new VirtualUIFrame();
		m_oTemplateBuilder.buildFrame(oCoverFrame, "fraCoverFrame");
		this.attachChild(oCoverFrame);
		
		// Initial the frame to cover other frame
		m_oFrameFirstPage = new FrameFirstPage();
		m_oTemplateBuilder.buildFrame(m_oFrameFirstPage, "fraFirstPage");
		//Juliezhang_20190412 start task2
		m_oFrameFirstPage.addInputListTitle();
		//Juliezhang_20190412 end
		m_oFrameFirstPage.setVisible(true);
		m_oFrameFirstPage.addListener(this);
		
		this.attachChild(m_oFrameFirstPage);
	}

	/*
	 * public void addCheckListPanel() {
	 * 
	 * }
	 */
	@Override
	public void frameFirstPage_clickOK() {
		// Finish showing this form
		this.finishShow();
	}
	
}
